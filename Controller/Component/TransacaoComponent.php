<?php

/**
 * TransacaoComponent
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
require_once APP . 'Vendor/gerencianet-api-v1.0/autoload.php';

use Gerencianet\Exception\GerencianetException;
use Gerencianet\Gerencianet;

class TransacaoComponent extends Component
{
    public $components = ['GLog'];
    private $config;
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
    private $transaction_id;
    private $custom_id;

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
        self::getConfig();
    }

    private function getConfig($sfx = null)
    {
        $this->config = Configure::read('Gerencianet');

        if ($this->sandbox):
            $sfx = '_devel';
        endif;

        return $this->options = [
                'client_id'     => $this->config['client']['id' . $sfx],
                'client_secret' => $this->config['client']['secret' . $sfx],
                'sandbox'       => $this->sandbox
            ];
    }

    /**
     * Muda a flag para desenvolvimento ou produção
     * @param $flag boolean
     */
    public function sandbox($flag)
    {
        $this->sandbox = $flag;

        self::getConfig();
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
            'name'          => $nome,
            'email'         => $email,
            'cpf'           => $cpf,
            'phone_number'  => $telefone,
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
     public function addItem($nome, $qtd, $valor, $marketplace = null)
     {
         $item = [
             'name'     => $nome,
             'amount'   => $qtd,
             'value'    => intval(str_replace([',', '.'], '', $valor))
         ];

         if (!empty($marketplace)):
             $item = array_merge($item, ['marketplace' => $marketplace]);
         endif;

         return array_push($this->items, $item);
     }

    /**
     * Cria transação por boleto
     * @param array $dados
     * @return array
     */
    private function boleto($dados = array())
    {
        return [
            'banking_billet' => [
                'expire_at' => $dados['vencimento'],
                'customer'  => $this->customer
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
            'street'        => 'Av JK',
            'number'        => 909,
            'neighborhood'  => 'Bauxita',
            'zipcode'       => '35400000',
            'city'          => 'Ouro Preto',
            'state'         => 'MG',
        ];

        return ['credit_card'       => [
                'installments'      => 1,
                'billing_address'   => $billingAddress,
                'payment_token'     => $token,
                'customer'          => array_filter($this->customer)
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
                $this->transaction_id = $this->charge['transacao']['data']['charge_id'];
                $this->params += ['id' => intval($this->transaction_id)];

                self::upPagamento();

                self::upMetadados();
            endif;
        } catch (GerencianetException $e) {
            $this->GLog->colector($e->code);
            $this->GLog->colector($e->error);
            $this->GLog->colector($e->errorDescription);
        } catch (Exception $e) {
            $this->GLog->colector($e->getMessage());
        }
    }

    /**
     * Configura pagamento
     * A api não permite mais de uma operação com o mesmo objeto estanciado,
     * por isso é criado um novo para cada operação
     */
    private function upPagamento()
    {
        try {
            $api = new Gerencianet($this->options);
            $this->charge['pagamento'] = $api->payCharge($this->params, $this->payment);
        } catch (GerencianetException $e) {
            $this->GLog->colector($e->code);
            $this->GLog->colector($e->error);
            $this->GLog->colector($e->errorDescription);
        } catch (Exception $e) {
            $this->GLog->colector($e->getMessage());
        }

        $this->GLog->errorHandler();
    }

    /**
     * Configura custom id e url retorno
     */
    private function upMetadados()
    {
        try {
            $api = new Gerencianet($this->options);
            $this->metadata += ['custom_id' => $this->custom_id];
            $this->charge['metadados'] = $api->updateChargeMetadata($this->params, $this->metadata);
        } catch (GerencianetException $e) {
            $this->GLog->colector($e->code);
            $this->GLog->colector($e->error);
            $this->GLog->colector($e->errorDescription);
        } catch (Exception $e) {
            $this->GLog->colector($e->getMessage());
        }

        $this->GLog->errorHandler();
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
            $this->GLog->colector($date . $e->code);
            $this->GLog->colector($e->error);
            $this->GLog->colector($e->errorDescription);
        } catch (Exception $e) {
            $this->GLog->colector($date . $e->getMessage());
        }

        $this->GLog->errorHandler();
    }

    /**
    * Cancela uma transação
    */
    public function cancelar($transaction_id)
    {
        $this->params = ['id' => intval($transaction_id)];

        try {
            $api = new Gerencianet($this->options);
            $this->charge = $api->cancelCharge($this->params, []);
        } catch (GerencianetException $e) {
            $this->GLog->colector($e->code);
            $this->GLog->colector($e->error);
            $this->GLog->colector($e->errorDescription);
        } catch (Exception $e) {
            $this->GLog->colector($e->getMessage());
        }

        $this->GLog->errorHandler();
    }

    /**
     * retorna a resposta do Gerencianet
     * @return array
     */
    public function getRetorno()
    {
        return $this->charge;
    }

    /**
     * Coleta e retorna o id da transação
     */

    public function id()
    {
        return $this->transaction_id;
    }

    /**
     * Retorna dados boleto
     * @return array
     */
    public function getBoleto()
    {
        if (!empty($this->charge)):
            return [
                'custom_id'  => $this->custom_id,
                'charge_id'  => $this->transaction_id,
                'codigo'     => $this->charge['pagamento']['data']['barcode'],
                'link'       => $this->charge['pagamento']['data']['link'],
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
            $i                    = count($this->charge['data']);
            $lastStatus           = $this->charge['data'][$i-1];
            $status               = $lastStatus["status"];
            $this->transaction_id = $lastStatus["identifiers"]["charge_id"];
            $this->custom_id      = $lastStatus['custom_id'];
            return [
                'transacao_id'  => $this->transaction_id,
                'custom_id'     => $this->custom_id,
                'status'        => $status["current"]
            ];
        endif;
    }

}
