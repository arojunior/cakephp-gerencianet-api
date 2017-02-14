<?php

class GLogComponent extends Component
{
    private $err = array();

    public function colector($err)
    {
        $this->err = $err;
    }

    /**
    * Trata os possíveis erros
    */
    public function errorHandler()
    {
        if (!empty($this->err)):
            throw new Exception(json_encode($this->err));
            self::logWritter();
            unset($this->err);
        endif;
    }

    /*
    * Escreve os erros no arquivo de log
    */
    private function logWritter()
    {
        $fp = fopen(TMP . DS . 'logs' . DS . 'gerencianet.log', 'a');
        fwrite($fp, print_r($this->err, TRUE));
        fclose($fp);
        $this->err = null;
    }
}
