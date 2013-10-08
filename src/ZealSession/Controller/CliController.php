<?php

namespace ZealSession\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Db\Sql\Sql;

class CliController extends AbstractActionController
{
    public function expireAction()
    {
        $verbose = $this->params()->fromRoute('verbose');

        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');

        $sql = new Sql($db);

        $delete = $sql->delete();

        $delete->from('sessions')
               ->where('expires < NOW()');

        $statement = $sql->prepareStatementForSqlObject($delete);
        $result = $statement->execute();

        if ($result && $verbose) {
            echo "Deleted ".number_format($result->count())." session(s)\n";
        }

        exit;
    }
}
