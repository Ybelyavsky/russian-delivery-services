<?php

/* Расчет доставки ТК ПЭК */
// Калькулятор выдает ошибку, если груз не проходит по габаритам, выходит общая ошибка калькулятора
// https://kabinet.pecom.ru/profile
// https://pecom.ru/business/developers/api_public/
// http://apiurl/getTariff?locationFrom=Москва&locationTo=Подольск&weight=1&volume=0.001
// https://phpjs.ru/пэк-публичный-апи-для-расчета-стоимос/
// https://pecom.ru/easyway/api/doc/
// https://pecom.ru/info/reduction/
// https://kabinet.pecom.ru/api/v1
// Тестовый расчет https://pecom.ru/services-are/shipping-request/?ocrguid=fc314edd-0dfb-4899-938e-c3b69533c1a0


class Pec implements DeliveryInterface
{
    private $apiUrl    = "http://calc.pecom.ru/bitrix/components/pecom/calc/ajax.php";  // базовый URL для GET запроса
    private $insurance = 1.3;                                                           // запас к цене доставки на погрешность

    protected $tranzit; // транзит
    protected $data;    // данные груза, габариты и т.п

    // Макссив с кодами городов
    private $towns = array("lyubertsy"   => 66995, // Красково (Люберецкий р-н)
                           "moskva"      => -446,
                           "spb"         => -463,
                           "vladimir"    => -147316,
                           "volgograd"   => -496,
                           "voronezh"    => -466,
                           "ekb"         => -473,
                           "ivanovo"     => -92885,
                           "kazan"       => -484,
                           "kaluga"      => -134714,
                           "krasnodar"   => -447,
                           "nn"          => -58740,
                           "novosibirsk" => -461,
                           "pskov"       => -147798,
                           "rnd"         => -452,
                           "ryazan"      => -240722,
                           "samara"      => -481,
                           "smolensk"    => -94539,
                           "stavropol"   => -456,
                           "tver"        => -175508,
                           "tula"        => -144716,
                           "ufa"         => -454,
                           "chelyabinsk" => -455,
                           "yaroslavl"   => -483);


    public function __construct(Tranzit $tranzit, array $data)
    {
        $this->tranzit = $tranzit->calc(); // расчет транзита
        $this->data = $data;               // данные груза, габариты и т.п
    }

    // Метод для расчета цены
    public function calc()
    {
        $townFrom = $this->towns[$this->data["from"]]; // откуда
        $townTo   = $this->towns[$this->data["to"]];   // куда

        // Параметры GET запроса
        $query = [
            'places' => [],
            'take' => [
                'town' => $townFrom, // Откуда
                  // 'tent' => 0,
                  // 'gidro' => 0,
                  // 'manip' => 0,
                  // 'speed' => 0,
                  // 'moscow' => 0
            ],
            'deliver' => [
                'town' => $townTo, // Куда
                  // 'tent' => 0,
                  // 'gidro' => 0,
                  // 'manip' => 0,
                  // 'speed' => 0,
                  // 'moscow' => 0
            ],
                // 'plombir' => 0,
                // 'strah' => 6800,
                // 'ashan' => 0,
                // 'night' => 0,
                // 'pal'  => 0,
                // 'pallets' => 0
        ];

        // Добавляем паллеты в массив с запросом
        $i = $this->data["pallet"];
        while ($i > 0) {
            array_push(
                $query['places'],
                [
                    $this->data["lenght"],  // длина м.
                    $this->data["width"],   // ширина м.
                    $this->data["height"],  // высота м.
                    $this->data["volume"],  // объём м3.
                    $this->data["weight"],  // вес кг.
                    1,                      // признак негабаритности груза
                    0                       // признак ЗУ - защитная транспортировочная упаковка стандартная
                ]
            );

            $i--;
        }

        // API запрос
        $result = $this->getApiRequest($query);

        // Проверка на негабрит
        $auto = ($result["autonegabarit"][2] == null) ? $result["auto"][2] : $result["autonegabarit"][2];

        // Себестоимость ПЭК
        $cost = $auto + $result["take"][2] + $result["deliver"][2] + $result["ADD_3"][3];

        $delivery = array("name"               => "ПЭК",
                          "deliveryWarehouse"  => $this->tranzit["price"],
                          "cost"               => round($cost),
                          "price"              => ($this->tranzit["price"] + $cost) * $this->insurance,
                          "time"               => $result["periods_days"],
                          "tranzit"            => $this->tranzit["tranzit"],
                          "terms"              => "pickup",
                          "type"               => "в ПВЗ ПЭК");


        // Если нет цены, обнуляем
        if ($delivery["cost"] == null) {
            $delivery = null;
        }

        return ($delivery);
    }


    // Получение списка городов
    public static function get_towns()
    {
        $result = file_get_contents("https://pecom.ru/ru/calc/towns.php", false);
        $result = json_decode($result, true);


        dump($result);
        // dump($result["Екатеринбург"]);
    }


    // Получить данные по API
    private function getApiRequest(array $query)
    {
        // Создаем URL запроса
        $query = http_build_query($query);
        $urlQuery = $this->apiUrl . '?' . $query;

        // Пытаемся получить ответ от сервера
        $result = @file_get_contents($urlQuery, false);  // отправляем запрос

        if ($result === false) {
            throw new Exception("API ПЭК недоступен" . PHP_EOL);
        } else {
            $result = json_decode($result, true); // обрабатываем запрос
        }

        // Проверка, если ошибку выдал API сервера - не используем т.к всегда кидает ошибку, если Авиа не доступно
        // if ($result["error"]) {
        //     throw new Exception("Ошибка в расчете доставки ПЭК " . $result["error"][0] . PHP_EOL);
        // }


        return ($result);
    }
} // . конец класса расчет доставки
