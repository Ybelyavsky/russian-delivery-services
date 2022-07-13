<?php

/* Расчет доставки Shiptor */
// Если не проходит, выдает null
// https://shiptor.ru/help/integration/api/api-cases#article_4
// https://shiptor.ru/doc/#api-Public
// Описание запрос-ответ https://shiptor.ru/doc/#api-Public-calculateShipping

// Список городов https://shiptor.ru/doc/#api-Public-getSettlements
// КЛАДР https://kladr-rf.ru

// https://ruseller.com/lessons.php?rub=37&id=2358
// https://qna.habr.com/q/669352

class Shiptor implements DeliveryInterface
{
    private $apiUrl    = "https://api.shiptor.ru/public/v1"; // Базовый URL для POST запроса
    private $insurance = 1.3;                                // Запас к цене доставки на погрешность

    protected $tranzit; // транзит
    protected $data;    // данные груза, габариты и т.п

    // Макссив с кодами городов
    private $towns = array("lyubertsy"   => "5001700300001", // Красково (Люберецкий р-н)
                           "moskva"      => "7700000000000",
                           "spb"         => "7800000000000",
                           "vladimir"    => "3300000100000",
                           "volgograd"   => "3400000100000",
                           "voronezh"    => "3600000100000",
                           "ekb"         => "6600000100000",
                           "ivanovo"     => "3700000100000",
                           "kazan"       => "1600000100000",
                           "kaluga"      => "4000000100000",
                           "krasnodar"   => "2300000100000",
                           "nn"          => "5200000100000",
                           "novosibirsk" => "5400000100000",
                           "pskov"       => "6000000100000",
                           "rnd"         => "6100000100000",
                           "ryazan"      => "6200000100000",
                           "samara"      => "6300000100000",
                           "smolensk"    => "6700000300000",
                           "stavropol"   => "2600000100000",
                           "tver"        => "6900000100000",
                           "tula"        => "7100000100000",
                           "ufa"         => "0200000100000",
                           "chelyabinsk" => "7400000100000",
                           "yaroslavl"   => "7600000100000");


    public function __construct(Tranzit $tranzit, array $data)
    {
        $this->tranzit = $tranzit->calc(); // расчет транзита
        $this->data    = $data;            // данные груза, габариты и т.п
    }

    // Метод для расчета цены
    public function calc()
    {
        $townFrom = $this->towns[$this->data["from"]]; // откуда
        $townTo   = $this->towns[$this->data["to"]];   // куда

        // Тип забора
        $pickUpType = "from-provider";  // "courier", "terminal", "independently-to-shiptor", "from-provider"

        // Запрос для расчета
        $query = array(
                        "id" => "JsonRpcClient.js",
                        "jsonrpc" => "2.0",
                        "method" => "calculateShipping",
                        "params" => array(
                            // "stock"         => false,
                            "kladr_id_from" => $townFrom,      // КЛАДР населенного пункта отправителя
                            "kladr_id"      => $townTo,          // КЛАДР населенного пункта получателя
                            "pick_up_type"  => $pickUpType,    // тип забора
                            // "courier"       => $courier,        // курьерские службы
                            "length"        => $this->data["lenght"],
                            "width"         => $this->data["width"],
                            "height"        => $this->data["height"],
                            "weight"        => $this->data["weight"],
                            "cod"           => 0,
                            "declared_cost" => 0
                        )
                    );

        // API запрос
        $result = $this->getApiRequest($query);

        $shiptor = $result["result"]["methods"];

        // Удаляем варианты доставки, которые не подходят
        // delivery_point, to-door, delivery-point-to-delivery-point
        $result = []; // объявляем массив

        foreach ($shiptor as $key) {
            if ($key["method"]["category"] == "to-door") {
                array_push($result, $key);
            }
        }
        $result = $result[0];  // оставляем, самый дешевый вариант

        $delivery = array("name"               => $result["method"]["name"],
                          "deliveryWarehouse" => $this->tranzit["price"],
                          "cost"               => $result["cost"]["total"]["sum"],
                          "price"              => ($this->tranzit["price"] + $result["cost"]["total"]["sum"]) * $this->insurance,
                          "time"               => $result["max_days"],
                          "tranzit"            => $this->tranzit["tranzit"],
                          "terms"              => "delivery",
                          "type"               => $result["method"]["name"] . " до двери ");

        // Расчет идет до одного паллета
        if ($this->data["pallet"] > 1) {
            $delivery = null;
        }

        // Если нет цены, обнуляем
        if ($delivery["cost"] == null) {
            $delivery = null;
        }


        return ($delivery);
    }


    // Получение списка городов
    public static function get_towns()
    {
        $query = array(
                        "id" => "JsonRpcClient.js",
                        "jsonrpc" => "2.0",
                        "method" => "getSettlements",
                        "params" => array(
                            "per_page" => 10,
                            "page" => 1,
                            "types" => array(
                                "Город"
                            ),
                            "level" => 2,
                            "parent" => "02000000000",
                            "country_code" => "RU"
                        )
                      );


        // Заголовок POST запроса
        $query   = json_encode($query, JSON_UNESCAPED_UNICODE); // массив в json
        $options = array('http' =>
                                  array(
                                    'method'  => 'POST',
                                    'header'  => 'Content-type:application/json',
                                    'content' => $query
                                  )
                            );


        // Запрос в Shiptor
        $context = stream_context_create($options);
        $result  = file_get_contents(self::$url, false, $context);
        $result  = json_decode($result, true);

        dump($result);
    }


    // Получить данные по API
    private function getApiRequest(array $query)
    {
        // Заголовок POST запроса
        $query   = json_encode($query, JSON_UNESCAPED_UNICODE); // массив в json
        $options = array('http' =>
                                array(
                                  'method'  => 'POST',
                                  'header'  => 'Content-type:application/json',
                                  'content' => $query
                                )
                        );


        // Запрос в Shiptor
        $context  = stream_context_create($options);

        // Пытаемся получить ответ от сервера
        $response = @file_get_contents($this->apiUrl, false, $context);  // отправляем запрос
        if ($response === false) {
            throw new Exception("API Shiptor недоступен" . PHP_EOL);
        } else {
            $result = json_decode($response, true); // обрабатываем запрос
        }


        return ($result);
    }
} // . конец класса расчет доставки
