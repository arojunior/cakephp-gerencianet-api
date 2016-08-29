# cakephp-gerencianet-api

Plugin de integração da API do Gateway Gerencianet com o Framework Cakephp 2.x.
Métodos para checkout transparamente por meio de Boletos

```php
/*
 * Este plugin depende da API disponível em https://github.com/gerencianet/gn-api-sdk-php/
 * @version 1.0
 *
 * PHP >= 5.4.0
 */
```

# Como usar

O padrão do plugin é true. Você deve utilizar este metódo passando o parametro false para produção. (aconselho a alterar o padrão após concluir os testes)
```php
 $this->Transacao->sandbox(false);
```
Adiciona os dados do cliente (campos e-mail e telefone são opcionais)
```php
$this->Transacao->setCliente('Junior Oliveira', '00000000000', 'contato@arojunior.com', '4899999999');
```
Adiciona os itens na transação
```php
$this->Transacao->addItem('Servico de informatica', 1, 1000);
```
Configura a url de retorno. Caso não seja informado, utiliza a configurada diretamente no gerencianet
```php
$this->Transacao->setUrl('http://www.suaurl.com/gerencianet/gerencianet/retorno');
```
Informa a forma de pagamento e a data de vencimento (data somente para os casos de boleto)
```php
$this->Transacao->setPagamento('boleto', '2016-06-30');
```
Este método envia os dados para o Gerencianet, passando como parametro um id interno do seu sistema (opcional)
Os dados retornados estarão disponíveis nos dois métodos abaixo
```php
$this->Transacao->criar('1');        
```
Para obter todos os dados
```php
$this->set('retorno', $this->Transacao->getRetorno());
```
Para obter apenas os dados do boleto (código de barras e endereço para impressão)
```php
$boleto = $this->Transacao->getBoleto());
```

# Notificação
```php
    public function retorno()
    {
        if ($this->request->is('post')):
            /*
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
            $this->Transacao->sandbox(false);

            $notificacao = $this->Transacao->setToken($this->request->data['notification']);
            /*
             * Para obter todos os dados do retorno
             * https://github.com/gerencianet/gn-api-sdk-php/blob/master/docs/NOTIFICATION.md
             */
            $retorno = $this->Transacao->getRetorno();
        endif;
    }
```
