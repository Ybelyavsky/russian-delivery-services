<?php

/* Расчет доставки ТК Байкал Сервис */
// https://api.baikalsr.ru/restapi



class Baikalsr implements DeliveryInterface
{
    private $apiKey    = "e343988c2fed78b014a7e45544ed6b18"; // API ключ
    private $apiPas    = " "; // передается пустой пароль
    private $basicauth;

    private $apiUrl    = "https://api.baikalsr.ru/v2/calculator"; // базовый URL для GET запроса
    private $insurance = 1.3;                                     // запас к цене доставки на погрешность

    protected $tranzit; // транзит
    protected $data;    // данные груза, габариты и т.п

    // Макссив с кодами городов
    private $towns = array("lyubertsy"   => "36ce34f2-c23b-424b-86a6-58f7bcb3c982", // "Люберецкий район, Московская область"
                           "moskva"      => "0c5b2444-70a0-4932-980c-b4dc0d3f02b5",
                           "spb"         => "c2deb16a-0330-4f05-821f-1d09c93331e6",
                           "vladimir"    => "f66a00e6-179e-4de9-8ecb-78b0277c9f10",
                           "volgograd"   => "a52b7389-0cfe-46fb-ae15-298652a64cf8",
                           "voronezh"    => "5bf5ddff-6353-4a3d-80c4-6fb27f00c6c1",
                           "ekb"         => "2763c110-cb8b-416a-9dac-ad28a55b4402",
                           "ivanovo"     => "40c6863e-2a5f-4033-a377-3416533948bd",
                           "kazan"       => "93b3df57-4c89-44df-ac42-96f05e9cd3b9",
                           "kaluga"      => "b502ae45-897e-4b6f-9776-6ff49740b537",
                           "krasnodar"   => "7dfa745e-aa19-4688-b121-b655c11e482f",
                           "nn"          => "555e7d61-d9a7-4ba6-9770-6caa8198c483",
                           "novosibirsk" => "8dea00e3-9aab-4d8e-887c-ef2aaa546456",
                           "pskov"       => "2858811e-448a-482e-9863-e03bf06bb5d4",
                           "rnd"         => "c1cfe4b9-f7c2-423c-abfa-6ed1c05a15c5",
                           "ryazan"      => "86e5bae4-ef58-4031-b34f-5e9ff914cd55",
                           "samara"      => "bb035cc3-1dc2-4627-9d25-a1bf2d4b936b",
                           "smolensk"    => "d414a2e8-9e1e-48c1-94a4-7308d5608177",
                           "stavropol"   => "2a1c7bdb-05ea-492f-9e1c-b3999f79dcbc",
                           "tver"        => "c52ea942-555e-45c6-9751-58897717b02f",
                           "tula"        => "b2601b18-6da2-4789-9fbe-800dde06a2bb",
                           "ufa"         => "7339e834-2cb4-4734-a4c7-1fca2c66e562",
                           "chelyabinsk" => "a376e68d-724a-4472-be7c-891bdb09ae32",
                           "yaroslavl"   => "6b1bab7d-ee45-4168-a2a6-4ce2880d90d3");


    public function __construct(Tranzit $tranzit, array $data)
    {
        $this->tranzit   = $tranzit->calc();                                   // расчет транзита
        $this->data      = $data;                                              // данные груза, габариты и т.п
        $this->basicauth = base64_encode($this->apiKey . ":" . $this->apiPas); // передаются пустой пароль
    }

    // Метод для расчета цены
    public function calc()
    {
        $townFrom    = $this->towns[$this->data["from"]]; // откуда
        $townTo      = $this->towns[$this->data["to"]];   // куда

        $query = array(
          "Departure" => array( // Блок пункта отправления
              "CityGuid" => $townFrom, // Идентификатор из справочника населенных пунктов
              // "PickUp" => array( // Блок информации о заборе груза (не создается, если груз будет доставлен на терминал самостоятельно)
              //     "Street" => "0faa0cfa-dfe8-4f5a-a001-2727a41d7f21",
              //     "House" => "1",
              //     "Date" => "2019-04-02T00:00:00",
              //     "TimeFrom" => "09:00",
              //     "TimeTo" => "21:00",
              //     "Services" => array(
              //         13
              //     )
              // )
          ),
          "Destination" => array( // Блок пункта назначения
              "CityGuid" => $townTo,
              // "Delivery" => array( // Блок информации о доставке груза (не создается если груз будет забран с терминала самостоятельно)
              //     "Street" => "234d642f-880b-41e8-a9ec-b3811c0eb49b",
              //     "House" => "2",
              //     "TimeFrom" => "09:00",
              //     "TimeTo" => "18:00"
              // )
          ),
          "Cargo" => array( // Информация о грузах
              "SummaryCargo" => array( // Объект описывающий параметры груза
                  "Length"        => $this->data["lenght"],      // длина м.
                  "Width"         => $this->data["width"],       // ширина м.
                  "Height"        => $this->data["height"],      // высота м.
                  "Volume"        => $this->data["totalVolume"], // объём м3.
                  "Weight"        => $this->data["totalWeight"], // вес кг.
                  "Units"         => $this->data["pallet"],      // количество мест
                  "Oversized"     => 0,                          // габарит (0 - габарит, 1 – негабарит)
                  "EstimatedCost" => 1,                          // оценочная стоимость груза (в рублях)
                  // "Services" => array( // Массив id - услуг из справочника
                  //     25
                  // )
              )
          ),
          "Preference" => array( // Блок преференций для расчета скидок
              "AuthKey" => $this->apiKey, // Идентификатор из нашего ЛК
              "PartnerGUID" => "" // Идентификатор контрагента для расчета преференций
          )
        );

        // API запрос
        $result = $this->getApiRequest($query);

        // Цены
        $cost  = round($result["total"]);                                         // цена перевозки через ТК
        $price = ($this->tranzit["price"] + $result["total"]) * $this->insurance; // цена доставки для клиента


        $delivery = array("name"               => "Байкал Сервис",
                          "deliveryWarehouse"  => $this->tranzit["price"],
                          "cost"               => $cost,
                          "price"              => $price,
                          "time"               => $result["transit"]["int"],
                          "tranzit"            => $this->tranzit["tranzit"],
                          "terms"              => "pickup",
                          "type"               => "в ПВЗ Байкал Сервис");


        return ($delivery);
    }


    // Получение списка городов
    public static function get_towns()
    {
        // Поиск населенного пункта по части слова
        // GET https://test-api.baikalsr.ru/v1/fias/cities?text=<text>
        // guid нужен
        $town     = "Ярославль";
        $url      = "https://test-api.baikalsr.ru/v1/fias/cities?text=" . $town;
        $response = file_get_contents($url, false);
        $result   = json_decode($response, true);


        dump($result);
    }


    // Получить данные по API
    private function getApiRequest(array $query)
    {
        // Заголовок POST запроса
        $query   = json_encode($query, JSON_UNESCAPED_UNICODE); // массив в json
        $options = array('http' => array(
          'method'  => 'POST',
          'header'  => array(
            'Content-type:application/json',
            'Authorization: Basic '. $this->basicauth
          ),
          'content' => $query
          )
        );

        // Запрос в Байкал Сервис
        $context  = stream_context_create($options);

        // Пытаемся получить ответ от сервера
        $response = @file_get_contents($this->apiUrl, false, $context);  // отправляем запрос
        if ($response === false) {
            throw new Exception("API Байкал Сервис недоступен" . PHP_EOL);
        } else {
            $result = json_decode($response, true); // обрабатываем запрос
        }

        // Проверка, если ошибку выдал API сервера
        if ($result["error"]) {
            throw new Exception("Ошибка в расчете доставки Байкал сервис " . $result["error"] . PHP_EOL);
        }


        return ($result);
    }
} // . конец класса расчет доставки
