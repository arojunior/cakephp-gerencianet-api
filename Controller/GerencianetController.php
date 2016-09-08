<?php

/**
 * Description of GerencianetController
 *
 * Apenas como exemplo de utilização
 *
 * @author arojunior (contato@arojunior.com)
 * @authorURI http://arojunior.com
 */
App::uses('AppController', 'Controller');

class GerencianetController extends AppController
{

    public $components = array('Gerencianet.Transacao');

    public function checkout()
    {
        //$this->Transacao->sandbox(false);

        $this->Transacao->setCliente('Junior Oliveira', '00000000000', 'contato@arojunior.com', '4899999999');

        $this->Transacao->addItem('Servico de informatica', 1, 1000);

        $this->Transacao->setUrl(Router::fullbaseUrl() . '/gerencianet/gerencianet/retorno');

        $this->Transacao->setPagamento('boleto', ['vencimento' => '2016-06-30']);

        $this->Transacao->criar('1');

        /*
         * Formato do retorno
         * array(
         *  [transacao] => array(), // https://github.com/gerencianet/gn-api-sdk-php/blob/master/docs/CHARGE.md
         *  [pagamento] => array(), // https://github.com/gerencianet/gn-api-sdk-php/blob/master/docs/CHARGE_PAYMENT.md
         *  [metadados] => array([code] => '')
         * );
         */
        $this->set('retorno', $this->Transacao->getRetorno()); // Para obter todos os dados

        /**
         * Para obter apenas os dados do boleto (código de barras e endereço para impressão)
         *
         * array(
         *    [custom_id] => '',
         *    [charge_id] => '',
         *    [codigo] => '',
         *    [link] => '',
         *    [vencimento] => ''
         *  );
         *
         *
         */
        $boleto = $this->Transacao->getBoleto();
    }

    public function retorno()
    {
        if ($this->request->is('post')):
            /**
             * Para obter apenas os dados atualizados
             *
             * array(
             *    [custom_id] => '',
             *    [charge_id] => '',
             *    [status] => ''
             *  );
             *
             *
             */
            //$this->Transacao->sandbox(false);

            $notificacao = $this->Transacao->setToken($this->request->data['notification']);
            /*
             * Para obter todos os dados do retorno
             * https://github.com/gerencianet/gn-api-sdk-php/blob/master/docs/NOTIFICATION.md
             */
            $retorno = $this->Transacao->getRetorno();

            foreach ($retorno['data'] as $r):
                /*
                 * Seu método de tratamento do retorno
                 */
            endforeach;
        endif;
    }

}
