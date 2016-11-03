<?php

/**
 * Created by PhpStorm.
 * User: pavelcherednichenko
 * Date: 29.09.16
 * Time: 16:27
 */
class GeoTwo {

    const KEY = ''; //здесь ваш ключ sypexgeo

    const INT = 0;

    const STR = 1;

    private $db;

    private $ip = [];

    private $groupIp = [];

    private $fileNameUniq = '';

    private $fileName = '';

    public $needTest = false;

    public function ipSort() {
        if ($this->needTest) {
            $this->fileName = 'RussianIpTest.txt';
            $this->fileNameUniq = 'RussianIpTestUniq.txt';
        } else {
            $this->fileName = 'RussianIp.txt';
            $this->fileNameUniq = 'RussianIpUniq.txt';
        }
        $alreadySort = false;
        $ipUniq = @fopen($this->fileNameUniq, "r");
        if ($ipUniq) {
            echo("Найден файл уникальных IP - буду использовать его\n");
            $handle = $ipUniq;
            $alreadySort = true;
        } else {
            $handle = @fopen($this->fileName, "r");
        }
        if ($handle) {
            while (($buffer = fgets($handle, 4096)) !== false) {
                $this->ip[] = str_replace(["\n", "\r",], '', $buffer);
            }
            if (!feof($handle)) {
                echo "Error: unexpected fgets() fail\n";
            }
            fclose($handle);
        }
        print_r(PHP_EOL . "всего элементов: " . count($this->ip));
        if (!$alreadySort) {
            $this->ip = array_unique($this->ip);
            file_put_contents($this->fileNameUniq, implode("\n", $this->ip));
        }
        print_r(PHP_EOL . "уникальных элементов: " . count($this->ip) . PHP_EOL);
    }

    private function groupHundredElements() {
        $t = 0;
        $group = 0;
        foreach ($this->ip as $item) {
            $t++;
            if ($t >= 100) {
                $group++;
                $t = 0;
            }
            $this->groupIp[$group][] = $item;
        }
    }

    /**
     * @param array $ip
     * @return mixed
     */
    public function getPost(array $ip) {
        $ipString = implode(",", $ip);
        if (empty($ipString)) {
            return null;
        }
        $ch = curl_init();
        if ($this->needTest) {
            echo("Тестовый запуск \n");
            $url = "http://api.sypexgeo.net/json/" . $ipString;
        } else {
            $url = "http://api.sypexgeo.net/" . self::KEY . "/json/" . $ipString;
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);   // возвращает веб-страницу
        curl_setopt($ch, CURLOPT_HEADER, 0);           // не возвращает заголовки
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);         // таймаут ответа
        $json = curl_exec($ch);
        curl_close($ch);
        return $json;
    }

    public function writeInBd($from = null) {
        $this->groupHundredElements();
        $k = 0;
        $excluding = [];
        if ($from !== null) {
            foreach ($this->groupIp as $count => $ipList) {
                foreach ($ipList as $ip) {
                    if ($from == $ip) {
                        break 2;
                    }
                }
                $excluding[] = $count;
            }
        }
        if (isset($excluding)) {
            echo("Не будут записаны группы: " . implode(", ", $excluding) . "\n");
        }
        echo("\n ___Запись начата____ \n");
        foreach ($this->groupIp as $count => $ipList) {
            if (isset($excluding)) {
                if (in_array($count, $excluding)) {
                    continue;
                }
            }
            $result = $this->getPost($ipList);
            if ($result == null) {
                continue;
            }
            $decode = json_decode($result, true, 5);
            // если придет только один элемент- то завернем его в массив, как будто пришло много
            if (isset($decode['ip'])) {
                $decode = [$decode];
            }

            foreach ($decode as $item) {
                if ($item['city'] === null && $item['country'] === null) {
                    if (!empty($item['error'])) {
                        echo("Ошибка: " . $item['error'] . "\n");
                        var_dump($item);
                        return null;
                    } else {
                        echo("Нет информации по ip: " . $item['ip'] . "\n");
                    }
                    continue;
                }
                $values = [
                    'ip'                 => $this->convert($item['ip'], self::STR),
                    'city_id'            => $item['city']['id'],
                    'city_lat'           => $item['city']['lat'],
                    'city_lon'           => $item['city']['lon'],
                    'city_name_ru'       => $this->convert($item['city']['name_ru'], self::STR),
                    'city_name_en'       => $this->convert($item['city']['name_en'], self::STR),
                    'city_name_de'       => $this->convert($item['city']['name_de'], self::STR),
                    'city_name_fr'       => $this->convert($item['city']['name_fr'], self::STR),
                    'city_name_it'       => $this->convert($item['city']['name_it'], self::STR),
                    'city_name_es'       => $this->convert($item['city']['name_es'], self::STR),
                    'city_name_pt'       => $this->convert($item['city']['name_pt'], self::STR),
                    'city_okato'         => $this->convert($item['city']['okato'], self::STR),
                    'city_vk'            => $item['city']['vk'],
                    'city_population'    => $item['city']['population'],
                    'region_id'          => $item['region']['id'],
                    'region_lat'         => $item['region']['lat'],
                    'region_lon'         => $item['region']['lon'],
                    'region_name_ru'     => $this->convert($item['region']['name_ru'], self::STR),
                    'region_name_en'     => $this->convert($item['region']['name_en'], self::STR),
                    'region_name_de'     => $this->convert($item['region']['name_de'], self::STR),
                    'region_name_fr'     => $this->convert($item['region']['name_fr'], self::STR),
                    'region_name_it'     => $this->convert($item['region']['name_it'], self::STR),
                    'region_name_es'     => $this->convert($item['region']['name_es'], self::STR),
                    'region_name_pt'     => $this->convert($item['region']['name_pt'], self::STR),
                    'region_iso'         => $this->convert($item['region']['iso'], self::STR),
                    'region_timezone'    => $this->convert($item['region']['timezone'], self::STR),
                    'region_okato'       => $this->convert($item['region']['okato'], self::STR),
                    'region_auto'        => $this->convert($item['region']['auto'], self::STR),
                    'region_vk'          => $item['region']['vk'],
                    'region_utc'         => $item['region']['utc'],
                    'country_id'         => $item['country']['id'],
                    'country_iso'        => $this->convert($item['country']['iso'], self::STR),
                    'country_continent'  => $this->convert($item['country']['continent'], self::STR),
                    'country_lat'        => $item['country']['lat'],
                    'country_lon'        => $item['country']['lon'],
                    'country_name_ru'    => $this->convert($item['country']['name_ru'], self::STR),
                    'country_name_en'    => $this->convert($item['country']['name_en'], self::STR),
                    'country_name_de'    => $this->convert($item['country']['name_de'], self::STR),
                    'country_name_fr'    => $this->convert($item['country']['name_fr'], self::STR),
                    'country_name_it'    => $this->convert($item['country']['name_it'], self::STR),
                    'country_name_es'    => $this->convert($item['country']['name_es'], self::STR),
                    'country_name_pt'    => $this->convert($item['country']['name_pt'], self::STR),
                    'country_timezone'   => $this->convert($item['country']['timezone'], self::STR),
                    'country_area'       => $item['country']['area'],
                    'country_population' => $item['country']['population'],
                    'country_capital_id' => $item['country']['capital_id'],
                    'country_capital_ru' => $this->convert($item['country']['capital_ru'], self::STR),
                    'country_capital_en' => $this->convert($item['country']['capital_en'], self::STR),
                    'country_cur_code'   => $this->convert($item['country']['cur_code'], self::STR),
                    'country_phone'      => $this->convert($item['country']['phone'], self::STR),
                    'country_neighbours' => $this->convert($item['country']['neighbours'], self::STR),
                    'country_vk'         => $item['country']['vk'],
                    'country_utc'        => $item['country']['utc'],
                ];
                $query = "INSERT INTO info (" . implode(", ", array_keys($values)) . ") VALUES (" . implode(", ", $values) . ");";
                if (mysqli_query($this->db, $query)) {
                    $k++;
                } else {
                    $error = mysqli_error($this->db);
                    echo "Error: " . $query . "\n" . $error;
                    var_dump($error);
                    return null;
                }
            };
            echo("Обработана группа: " . $count . "\n");
            if (isset ($ipList[count($ipList) - 1])) {
                echo("Последний IP группы: " . $ipList[count($ipList) - 1] . "\n");
            }
            echo("Записано: " . $k . "\n");
        }
    }

    /**
     * @param $convert string|int
     * @param $to int
     * @return string|int
     */
    private function convert($convert, $to) {
        if ($to == self::INT) {
            return $convert;
        }
        if ($to == self::STR) {
            $convert = mysqli_real_escape_string($this->db, $convert);
            return "'{$convert}'";
        }
        return $convert;
    }

    public function __construct() {
        $this->db = mysqli_connect('127.0.0.1:3306', 'root', 'admin', 'cities') or die('Error connecting to MySQL server.');
        $this->db->set_charset("utf8");
        mysqli_query($this->db, "SET NAMES 'utf8';");
        if ($this->db) {
            echo("Соединение создано\n");
        }
    }

    public function clearTable($table) {
        mysqli_query($this->db, "TRUNCATE " . $table);
        echo("Таблица " . $table . " очищена\n");
    }
}
