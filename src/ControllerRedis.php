<?php

namespace redisaggregate;

use redisaggregate\ModelRedis;
 
class ControllerRedis {
/*
 * sergechurkin/restapi
*/
    public function run($params) {
        $model = new ModelRedis();
        $model->page   = (string)filter_input(INPUT_GET, 'page');
        if (empty($model->page)) {
            $model->page = 'home';
        }
        $model->action = (string)filter_input(INPUT_GET, 'action');
        if (empty($model->action)) {
            $model->action = 'menu';
        }
        if (!empty((string)filter_input(INPUT_POST, 'action'))) {
            $model->action = (string)filter_input(INPUT_POST, 'action');
        }
        /* Возможен вызов следующих методов:
         * home_menu
         * home_gen
         * home_goods
         * home_sales
         * home_aggr
         * home_validate
         */
        $method = $model->page . '_' . $model->action;
        $model->benchmark = microtime(true);
        $model->memmark = memory_get_usage();
        $model->filename = 'log.txt';
        if(method_exists($model, $method)) {
            $model->$method ($params);
        } else {
            $model->putError(405, 'Задан недопустимый метод', strtoupper($method), $params);
        }                               
        $model->closeForm($params);
    }
}
