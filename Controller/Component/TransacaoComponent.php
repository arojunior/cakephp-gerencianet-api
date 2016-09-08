<?php

/**
 * Description of TransacaoComponent
 *
 * Plugin de integração da API do Gateway Gerencianet com o Framework Cakephp
 * Métodos para checkout transparamente por meio de Boletos
 *
 * API disponível em https://github.com/gerencianet/gn-api-sdk-php/
 * @version 1.0
 *
 * PHP >= 5.4.0
 *
 *
 * @author arojunior (contato@arojunior.com)
 * @authorURI http://arojunior.com
 * @license MIT (http://opensource.org/licenses/MIT)
 */
require_once APP . '/Vendor/gerencianet-api-v1.0/autoload.php';

use Gerencianet\Exception\GerencianetException;
use Gerencianet\Gerencianet;

class TransacaoComponent extends Component
{

    private $options;
    private $sandbox = true;
    private $items = array();
    private $customer;
    private $body = array();
    private $params = array();
    private $metadata = array();
    private $payment = array();
    private $payment_types = array('boleto', 'cartao');
    private $charge = array();
    private $transacao_id;
    private $custom_id;
    private $err;

    /**
     * Inicializa a classe configurando os dados coletados no arquivo Config\boostrap.php
     *
     * Configure::write('Gerencianet.client', array(
     *   'id' => '',
     *   'secret' => '',
     *   'id_devel' => '',
     *   'secret_devel' => ''
     *   ));
     *
     */
    public function __construct()
    {
        $config = Configure::read('Gerencianet');
        if ($config):

            if ($this->sandbox):
                $sfx = '_devel';
            else:
                $sfx = null;
            endif;

            $this->options = [
                'client_id' => $config['client']['id' . $sfx],
                'client_secret' => $config['client']['secret' . $sfx],
                'sandbox' => $this->sandbox
            ];

        endif;
    }

    /**
     * Muda a flag para desenvolvimento ou produção
     * @param $flag boolean
     */
    public function sandbox($flag)
    {
        $this->options['sandbox'] = $flag;
    }

    /**
     * Dados do cliente
     * @param string $nome
     * @param string $cpf
     * @param string $email (opcional)
     * @param string $telefone (opcional)
     */
    public function setCliente($nome, $cpf, $email = null, $telefone = null)
    {
        $this->customer = [
            'name' => $nome,
            'email' => $email,
            'cpf' => $cpf,
            'phone_number' => $telefone,
        ];
    }

    /**
     * Configura a url de retorno da transação
     * Caso não seja informado, envia para a url padrão
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->metadata += ['notification_url' => $url];
    }

    /**
     * Aplica o metodo de pagamento informado
     * @param string $metodo (boleto ou cartão)
     * @param array dados
     */
    public function setPagamento($metodo, $dados = array())
    {

        $metodo = strtolower($metodo);

        if (in_array($metodo, $this->payment_types)):

            $pay = call_user_func($metodo, $dados);

        endif;

        $this->payment = ['payment' => $pay];
    }

    /**
     * Adiciona os itens a transação
     * @param string $nome
     * @param int $qtd
     * @param int $valor (o valor deve ser informado como integer, não como float ou number.
     * ex: R$ 10,00 deve ser informado 1000)
     */
    public function addItem($nome, $qtd, $valor)
    {
        array_push($this->items, [
            'name' => $nome,
            'amount' => $qtd,
            'value' => intval(str_replace([',', '.'], '', $valor))
        ]);
    }

    /**
     * Cria transação por boleto
     * @param array $dados
     * @return array
     */
    private function boleto($dados = array())
    {
        return ['banking_billet' => [
                'expire_at' => $dados['vencimento'],
                'customer' => $this->customer
            ]
        ];
    }

    /**
     * Apenas exemplo
     * Precisa implementar corretamente
     */
    private function cartao($dados = array())
    {
        $token = '6426f3abd8688639c6772963669bbb8e0eb3c319';

        $billingAddress = [
            'street' => 'Av JK',
            'number' => 909,
            'neighborhood' => 'Bauxita',
            'zipcode' => '35400000',
            'city' => 'Ouro Preto',
            'state' => 'MG',
        ];

        return ['credit_card' => [
                'installments' => 1,
                'billing_address' => $billingAddress,
                'payment_token' => $token,
                'customer' => array_filter($this->customer)
            ]
        ];
    }

    /**
     * Cria transação
     * @param string $custom_id (apesar de usualmente utilizarmos números,
     * o valor deve ser informado em formato string)
     */
    public function criar($custom_id = null)
    {
        $this->body += ['items' => $this->items];
        $this->custom_id = $custom_id;

        try {
            $api = new Gerencianet($this->options);
            // Cria a cobrança
            $this->charge['transacao'] = $api->createCharge($this->params, $this->body);

            if (!empty($this->charge)):
                $this->transacao_id = $this->charge['transacao']['data']['charge_id'];
                $this->params += ['id' => intval($this->transacao_id)];

                self::upPagamento();

                self::upMetadados();
            endif;
        } catch (GerencianetException $e) {
            $this->err .= $e->code;
            $this->err .= $e->error;
            $this->err .= $e->errorDescription;
        } catch (Exception $e) {
            $this->err .= $e->getMessage();
        }

        self::errorHandler();
    }

    /**
     * Configura pagamento
     * A api não permite mais de uma operação com o mesmo objeto estanciado,
     * por isso é criado um novo para cada operação
     */
    private function upPagamento()
    {
        $api = new Gerencianet($this->options);
        $this->charge['pagamento'] = $api->payCharge($this->params, $this->payment);
    }

    /**
     * Configura custom id e url retorno
     */
    private function upMetadados()
    {
        $api = new Gerencianet($this->options);
        $this->metadata += ['custom_id' => $this->custom_id];
        $this->charge['metadados'] = $api->updateChargeMetadata($this->params, $this->metadata);
    }

    /**
     * Envia o token para coletar as notificações de retorno da Gerencianet
     */
    public function setToken($token)
    {
        $this->params = ['token' => $token];
        $date = '[' . date('d/m/Y H:i:s') . '] ';

        try {

            $api = new Gerencianet($this->options);
            $this->charge = $api->getNotification($this->params, []);
        } catch (GerencianetException $e) {
            $this->err .= $date . $e->code;
            $this->err .= $e->error;
            $this->err .= $e->errorDescription;
        } catch (Exception $e) {
            $this->err .= $date . $e->getMessage();
        }

        self::errorHandler();
    }

    /*
    * Trata os possíveis erros
    */
    private function errorHandler()
    {
        if (!empty($this->err)):
            throw new Exception($this->err);
            self::logWritter();
        endif;
    }

    /*
    * Escreve os erros no arquivo de log
    */
    private function logWritter()
    {
        $fp = fopen(TMP . DS . 'logs' . DS . 'gerencianet.log', 'a');
        fwrite($fp, $this->err);
        fclose($fp);
    }

    /**
     * retorna a resposta do Gerencianet
     * @return array
     */
    public function getRetorno()
    {
        return $this->charge;
    }

    /*
     * Coleta e retorna o id da transação
     */

    public function id()
    {
        return $this->transacao_id;
    }

    /**
     * Retorna dados boleto
     * @return array
     */
    public function getBoleto()
    {
        if (!empty($this->charge)):
            return [
                'custom_id' => $this->custom_id,
                'charge_id' => $this->transacao_id,
                'codigo' => $this->charge['pagamento']['data']['barcode'],
                'link' => $this->charge['pagamento']['data']['link'],
                'vencimento' => $this->charge['pagamento']['data']['expire_at']
            ];
        endif;

        return null;
    }

    /**
     * Coleta as notificações de retorno da Gerencianet
     */
    public function getNotificacao()
    {
        /**
         * Formato do array recebido:
         * https://github.com/gerencianet/gn-api-sdk-php/blob/master/docs/NOTIFICATION.md
         */
        if (!empty($this->charge)):
            $this->transacao_id = $this->charge['data'][0]['identifiers']['charge_id'];
            $this->custom_id = $this->charge['data'][0]['custom_id'];
            return [
                'transacao_id' => $this->transacao_id,
                'custom_id' => $this->custom_id,
                'status' => $this->charge['data'][0]['status']['current']
            ];
        endif;
    }

}
