<?php

namespace redisaggregate;

// use redisaggregate\ControllerRedis;
/* **ext** */
use sergechurkin\cform\cForm;

class ModelRedis {

    public $page;
    public $action;
    public $benchmark;
    public $memmark;
    public $filename;
    private $cform;
    private $units  = [];
    private $providers  = [];
    private $goods  = [];
    private $bills  = [];
    private $sales  = [];
    private $fields  = [];
    private $buttons = [];
    private $count_entrys   = 0; // количество записей всего
    private $current_page   = 1; // текущая страница
    private $nfirstrec;    
    private $nlastrec;

    /*
     * Создание шапки
     */
    public function createForm() {
        $this->cform = new cform();
        $this->cform->bldHeader('Агрегирование данных в Redis');
    }
    /*
     * Отрисовка меню
     */
    public function bldMenu($num_select) {
        $arrmenu = [['Генерировать данные', '', './?page=home&action=gen'],
                    ['Товары', '', './?page=home&action=goods'],
                    ['Продажи', '', './?page=home&action=sales'],
                    ['Агрегирование продаж', '', './?page=home&action=aggr']];
        $arrmenu[$num_select][1] = 'active';
        $this->cform->bldMenu($arrmenu, ['', '', '']);
    }

    public function home_menu($params) {
        $this->createForm();
        $redis = $this->connect($params);
        if ($redis->get('user:admin:token') == $params['token']) {
            $this->bldMenu(1);
        } else {
            $this->bldMenu(0);
        }
        $this->cform->bldImg('redisaggregate.png');
        $redis->close();
    }
    /*
     * Таблицы задаем в виде строк Redis в формате json
     * 
     */
    public function createData($params) {
        $redis = $this->connect($params);
        $redis->flushdb(); // Обнуление всех ключей
        if ($params['debug']) {
            $log = 'deleteData ' . date("Y-m-d H:i:s") . ' ' .
                   'Использовано памяти Redis: ' . round($redis->info('memory')['used_memory']/1024/1024,2) . 'M' . ' ' . 
                   'Использовано памяти PHP: ' . round((memory_get_peak_usage(true) - $this->memmark)/1024/1024,2) . 'M' . ' ' . 
                   'Время генерации страницы: ' . round(microtime(true) - $this->benchmark, 3) . "\n";
            file_put_contents($this->filename, $log, FILE_APPEND | LOCK_EX);
            $this->benchmark = microtime(true);
        }
        $redis->set('user:admin:token', $params['token']);
        $this->units[1] = 'кг';
        $this->units[2] = 'шт.';
        $redis->set('user:admin:units', json_encode($this->units, JSON_UNESCAPED_UNICODE));
        for ($i = 1; $i <= 5; $i++) {
            $this->providers[$i] = 'Поставщик ' . $i;
        }
        $redis->set('user:admin:providers', json_encode($this->providers, JSON_UNESCAPED_UNICODE));
        $i_goods_id = 0;
        for ($i = 1; $i <= count($this->providers); $i++) {
            for ($j = 1; $j <= 100; $j++) {
                $i_goods_id++;
                $this->goods[$i_goods_id] = ['Товар ' . $i_goods_id, // name
                    $i, // providers_id
                    rand(1, 2), // units_id
                    rand(10, 1000), // price
                    ]; 
            }
        }
        $redis->set('user:admin:goods', json_encode($this->goods, JSON_UNESCAPED_UNICODE));
        $date_begin = date("Y-m-d", strtotime(date("Y-m-d")) - 29 * 86400);
        $date_end = date("Y-m-d");
        $date = $date_begin;
        $i_sales_id = 0;
        $i_bills_id = 0;
        while($date <= $date_end){
            $i_bills = 1000; // rand(100, 1000); // Количество чеков за дату
            for ($i = 1; $i <= $i_bills; $i++) {
                $i_bills_id++;
                $this->bills[$i_bills_id] = $date; // dt
                $i_sales = 10;//rand(1, 30); // Количество товаров в чеке
                for ($j = 1; $j <= $i_sales; $j++) {
                    $i_goods = rand(1, 500);
                    $i_sales_id++; 
                    $this->sales[$i_sales_id] = [$i_bills_id, $i_goods, 1, $this->goods[$i_goods][3], ];
                } 
            }
            $date = date('Y-m-d', strtotime($date) + 86400); // где 86400 - количество секунд в одном дне (24*60*60)
        }
        $redis->set('user:admin:bills', json_encode($this->bills, JSON_UNESCAPED_UNICODE));
        $redis->set('user:admin:sales', json_encode($this->sales, JSON_UNESCAPED_UNICODE));
        if ($params['debug']) {
            $log = 'createData ' . date("Y-m-d H:i:s") . ' ' .
                   'Ключей Redis: ' . $redis->info('keyspace')['db0'] . ' ' .
                   'Использовано памяти Redis: ' . round($redis->info('memory')['used_memory']/1024/1024,2) . 'M' . ' ' . 
                   'Использовано памяти PHP: ' . round((memory_get_peak_usage(true) - $this->memmark)/1024/1024,2) . 'M' . ' ' . 
                   'Время генерации страницы: ' . round(microtime(true) - $this->benchmark, 3) . "\n";
            file_put_contents($this->filename, $log, FILE_APPEND | LOCK_EX);
        }
        $redis->close();
        return 'Количество записей: bills = ' . $i_bills_id . ' goods = ' . $i_goods_id . ' sales = ' . $i_sales_id;
    }
    public function home_gen($params) {
        $this->createForm();
        $this->bldMenu(1);
        $rv = $this->createData($params);
        $this->cform->bldMessage(0, 'Данные сгенерированы. ' . $rv);
    }

    private function readArrTab($arrTab, $redis) {
        foreach($arrTab as $arr) {
            $this->$arr = json_decode($redis->get('user:admin:' . $arr), true);
        }
    }

    public function readCurPage() {
        $this->current_page = (string) filter_input(INPUT_GET, 'current_page'); 
        if (empty($this->current_page)) {
           $this->current_page = 1;
        }
    }
    public function initBrw($params) {
        $max_count_pages = ceil($this->count_entrys / $params['entry_on_page']);
        if ($this->current_page > $max_count_pages & $this->current_page > 1) {
            $this->current_page = $max_count_pages;
        }
        $this->nfirstrec = ($this->current_page - 1) * $params['entry_on_page'] + 1;
        $this->nlastrec = $this->nfirstrec + $params['entry_on_page'] - 1;
        if ($this->nlastrec > $this->count_entrys) {
            $this->nlastrec = $this->count_entrys;
        }
    }

    public function home_goods($params) {
        $this->readCurPage();
        $this->createForm();
        $this->bldMenu(1);
        $redis = $this->connect($params);
        $this->readArrTab(['goods', 'units', 'providers'], $redis);
        $this->fields = ['#', 'Наименование', 'Поставщик', 'Ед.изм.', 'Цена',];
        $this->count_entrys = count($this->goods);
        $this->initBrw($params);
        for ($i = $this->nfirstrec; $i <= $this->nlastrec; $i++) {
            $r[$i][0] = $i;
            $r[$i][1] = $this->goods[$i][0];
            $r[$i][2] = $this->providers[$this->goods[$i][1]];
            $r[$i][3] = $this->units[$this->goods[$i][2]];
            $r[$i][4] = $this->goods[$i][3];
        }
        $redis->close();
        $this->cform->bldTable('', 'Товары', 12, $this->fields, $r, [], 
                         $this->count_entrys, $params['entry_on_page'], $this->current_page, $params['max_pages_list'],
                         $this->nfirstrec, $this->nlastrec, '?page=home&action=goods&');
    }
    public function home_sales($params) {
        $this->readCurPage();
        $this->createForm();
        $this->bldMenu(2);
        $redis = $this->connect($params);
        $this->readArrTab(['bills', 'goods', 'sales', 'providers'], $redis);
        $this->fields = ['#', 'Дата', 'Поставщик', 'Чек', 'Товар', 'Количество', 'Сумма',];
        $this->count_entrys = count($this->sales);
        $this->initBrw($params);
        for ($i = $this->nfirstrec; $i <= $this->nlastrec; $i++) {
            $r[$i][0] = $i;                                                     // #
            $r[$i][1] = $this->bills[$this->sales[$i][0]];                      // Дата
            $r[$i][2] = $this->providers[$this->goods[$this->sales[$i][1]][1]]; // Поставщик
            $r[$i][3] = $this->sales[$i][0];                                    // Чек
            $r[$i][4] = $this->goods[$this->sales[$i][1]][0];                   // Товар
            $r[$i][5] = $this->sales[$i][2];                                    // Количество
            $r[$i][6] = $this->sales[$i][3];                                    // Сумма
        }
        $redis->close();
        $this->cform->bldTable('', 'Продажи', 12, $this->fields, $r, [], 
                         $this->count_entrys, $params['entry_on_page'], $this->current_page, $params['max_pages_list'],
                         $this->nfirstrec, $this->nlastrec, '?page=home&action=sales&');
    }
/*
 * Настройка агрегирования
 */
    public function getaggr_key() {
        $aggr_key = (string)filter_input(INPUT_POST, 'aggr_key');
        if (empty($aggr_key)) {
            $aggr_key = (string)filter_input(INPUT_GET, 'aggr_key');
        }
        return $aggr_key;
    }
    public function home_aggr($params) {
        $aggr_key = '';
        $this->fields[] = ['Агрегирование по колонкам:', 6, 'aggr_key', 5, 'text', $aggr_key, '', 'readonly="readonly"'];
        $this->buttons[] = ['Получить отчет', 'submit', 'btn btn-success', ''];
        $this->buttons[] = ['Очистить', 'button', 'btn btn-danger', 'document.getElementById(\'aggr_key\').value=\'\''];
        $this->buttons[] = ['Дата', 'button', 'btn btn-default', 'setKey(\'2\')'];
        $this->buttons[] = ['Поставщик', 'button', 'btn btn-default', 'setKey(\'3\')'];
        $this->buttons[] = ['Товар', 'button', 'btn btn-default', 'setKey(\'4\')'];
        $this->buttons[] = ['Чек', 'button', 'btn btn-default', 'setKey(\'5\')'];
        $this->createForm();
        $this->bldMenu(3);
        $this->cform->bldForm('', 'Настройка агрегирования', 6, $this->fields, $this->buttons);
        $script = '
            function putDep() {
                if (document.getElementById(\'aggr_key\').value != \'\') {
                    document.getElementById(\'aggr_key\').value = document.getElementById(\'aggr_key\').value + \':\';
                }
            }
            function setKey(i) {
                if (document.getElementById(\'aggr_key\').value.indexOf(":") !== -1) {
                    alert("Для ограничения объема получаемых данных допустимо добавлять только 2 колонки");
                    return;
                }
                if ( i == \'2\') {
                    if (document.getElementById(\'aggr_key\').value.indexOf("Дата") == -1) {
                        putDep();
                        document.getElementById(\'aggr_key\').value = document.getElementById(\'aggr_key\').value + \'Дата\';
                    } else {
                        alert("Дата уже добавлена");
                    }
                }    
                if ( i == \'3\') {
                    if (document.getElementById(\'aggr_key\').value.indexOf("Поставщик") == -1) {
                        putDep();
                        document.getElementById(\'aggr_key\').value = document.getElementById(\'aggr_key\').value + \'Поставщик\';
                    } else {
                        alert("Поставщик уже добавлен");
                    }
                }    
                if ( i == \'4\') {
                    if (document.getElementById(\'aggr_key\').value.indexOf("Товар") == -1) {
                        putDep();
                        document.getElementById(\'aggr_key\').value = document.getElementById(\'aggr_key\').value + \'Товар\'; 
                    } else {
                        alert("Товар уже добавлен");
                    }
                }    
                if ( i == \'5\') {
                    if (document.getElementById(\'aggr_key\').value.indexOf("Чек") == -1) {
                        putDep();
                        document.getElementById(\'aggr_key\').value = document.getElementById(\'aggr_key\').value + \'Чек\'; 
                    } else {
                        alert("Чек уже добавлен");
                    }
                }    
            }    
        ';
        echo '<script>' . $script . '</script>';
    }
    public function putSep($key_val) {
        if ($key_val === '') {
            return '';
        } else {
            return ':';
        }
    }

    public function home_validate($params) {
        $this->readCurPage();
        $aggr_key = $this->getaggr_key();
        $sort = (string)filter_input(INPUT_GET, 'sort');
        $this->createForm();
        $this->bldMenu(3);
        if (empty($aggr_key)) {
            $this->cform->bldMessage(2, 'Необходимо задать данные для агрегации');
            return;
        }    
        $redis = $this->connect($params);
        if ($sort === '') {
            $this->readArrTab(['bills', 'goods', 'sales'], $redis);
            $redis->del('user:admin:report');
        }    
        $this->fields = explode(':', $aggr_key);
        if ($sort === '') {
            foreach ($this->sales as $sal) {
                $key_val = '';
                foreach ($this->fields as $key) {
                    if ($key == 'Дата') {
                        $key_val = $key_val . $this->putSep($key_val) . $this->bills[$sal[0]];
                    }
                    if ($key == 'Товар') {
                        $key_val = $key_val . $this->putSep($key_val) . $sal[1];
                    }
                    if ($key == 'Поставщик') {
                        $key_val = $key_val . $this->putSep($key_val) . $this->goods[$sal[1]][1];
                    }
                    if ($key == 'Чек') {
                        $key_val = $key_val . $this->putSep($key_val) . $sal[0];
                    }
                }
                if ($key_val === '') {
                    continue;
                }
                if (!isset($aggr_arr[$key_val][0])) {
                    $aggr_arr[$key_val][0] = $sal[2];
                    $aggr_arr[$key_val][1] = $sal[3];
                } else {
                    $aggr_arr[$key_val][0] = $aggr_arr[$key_val][0] + $sal[2]; // quantity
                    $aggr_arr[$key_val][1] = $aggr_arr[$key_val][1] + $sal[3]; // sum
                }    
                if ((memory_get_usage() / 1024 / 1024) > $params['memory_limit']) {
                    $error_message = 'Из-за нехватки оперативной памяти выведены не все записи. Использовано:' .
                            round(memory_get_usage() / 1024 / 1024, 2) . 'M';
                    break;
                }
            }
            unset($this->sales);
            unset($this->goods);
            unset($this->bills);
            $redis->set('user:admin:report', json_encode($aggr_arr, JSON_UNESCAPED_UNICODE));
        } else {
            $aggr_arr = json_decode($redis->get('user:admin:report'), true);
        }
        $this->fields[] = 'Количество';
        $this->fields[] = 'Сумма';
        $s = [];
        $r = [];
        $i = 0;
        foreach($aggr_arr as $key=>$arr_q_s) {
            foreach (explode(':', $key) as $k=>$key_val) {
                $r[$i][$k] = $key_val;
                $s[$k][$i] = $key_val;
            }
            $r[$i][$k + 1] = $arr_q_s[0];
            $s[$k + 1][$i] = $arr_q_s[0];
            $r[$i][$k + 2] = $arr_q_s[1];
            $s[$k + 2][$i] = $arr_q_s[1];
            $i++;
            if ((memory_get_usage()/1024/1024) > $params['memory_limit']) {
                $error_message = 'Из-за нехватки оперативной памяти выведены не все записи. Использовано:' .
                        round(memory_get_usage()/1024/1024,2) . 'M';
                break;
            }
        }
        $this->count_entrys = $i;
        $this->initBrw($params);
        if ($sort === '') {
            if (!empty($r)) {
                array_multisort($s[0], $r);
            }    
        } else {
            if (!empty($r)) {
                array_multisort($s[$sort], $r);
            }    
        }
        for ($i = $this->nfirstrec; $i <= $this->nlastrec; $i++) {
            $r_view[$i - 1] = $r[$i - 1];
        }
        if (isset($error_message)) {
            $this->cform->bldMessage(1, $error_message);
        }
        if ($params['debug']) {
            $log = 'Aggr: ' . $aggr_key . date("Y-m-d H:i:s") . ' ' .
                   'Ключей Redis: ' . $redis->info('keyspace')['db0'] . ' ' .
                   'Использовано памяти Redis: ' . round($redis->info('memory')['used_memory']/1024/1024,2) . 'M' . ' ' . 
                   'Пик использовано памяти PHP: ' . round((memory_get_peak_usage(true) - $this->memmark)/1024/1024,2) . 'M' . ' ' . 
                   'Время генерации страницы: ' . round(microtime(true) - $this->benchmark, 3) . "\n";
            file_put_contents($this->filename, $log, FILE_APPEND | LOCK_EX);
        }
        $this->cform->bldTable('', 'Агрегированные данные', 12, $this->fields, $r_view, [], 
                $this->count_entrys, $params['entry_on_page'], $this->current_page, $params['max_pages_list'], 
                $this->nfirstrec, $this->nlastrec, '?page=home&action=validate&aggr_key=' . $aggr_key . '&', true, $sort);
    }
    
    public function connect($params) {
        try {
            $redis = new \Redis();
            $redis->connect($params['host'], $params['port']);
        } catch (RedisException $e) {
            throw new \RuntimeException('Не удалось установить соединение. ' . $e->getMessage());
        }
        return $redis;
    }    
    public function closeForm($params) {
        $redis = $this->connect($params);
        $txt = 'Использовано памяти Redis: ' . round($redis->info('memory')['used_memory']/1024/1024,2) . 'M' .
               ' Использовано памяти PHP: ' . round(memory_get_usage()/1024/1024,2) . 'M' .
               ' Выделено памяти PHP: ' . round(memory_get_usage(true)/1024/1024,2) . 'M' .
               ' Пик использования памяти PHP: ' . round(memory_get_peak_usage(true)/1024/1024,2) . 'M' .
               ' Лимит памяти PHP: ' . ini_get("memory_limit") .
               ' Время генерации страницы: ' . round(microtime(true) - $this->benchmark, 3);
        $redis->close();
        $this->cform->bldFutter($txt);
        $this->cform = null;
    }
}
