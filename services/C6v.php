<?php

/* Расчет доставки ТК C6v */
// Калькулятор не выдает ошибку, считает любой размер, т.к GTD всегда выдает цену
// https://pecom.ru/business/developers/api_public/
// $f3->route('GET|POST /delivery', function($f3) { dump(c6v::calc("moskva", "spb", 1.2, 0.8, 1, 33, 1, 1)); } );
// $f3->route('GET|POST /towns', function($f3)    { c6v::get_towns(); } );


class C6v implements DeliveryInterface
{
    private $apiKey    = "3ea47612bbfca62ec492f5ec4b8f1862e83ec1a9"; // API ключ
    private $apiUrl    = "http://api.c6v.ru/";                       // Базовый URL для GET запроса
    private $insurance = 1.3;                                        // Запас к цене доставки на погрешность

    protected $tranzit; // транзит
    protected $data;    // данные груза, габариты и т.п

    // Макссив с кодами городов
    private $towns = array("lyubertsy"   => "Люберцы (Московская область)", // Красково (Люберецкий р-н)
                           "moskva"      => "Москва",
                           "spb"         => "Санкт-Петербург",
                           "vladimir"    => "Владимир",
                           "volgograd"   => "Волгоград",
                           "voronezh"    => "Воронеж",
                           "ekb"         => "Екатеринбург",
                           "ivanovo"     => "Иваново",
                           "kazan"       => "Казань",
                           "kaluga"      => "Калуга",
                           "krasnodar"   => "Краснодар",
                           "nn"          => "Нижний Новгород",
                           "novosibirsk" => "Новосибирск",
                           "pskov"       => "Псков",
                           "rnd"         => "Ростов-на-Дону",
                           "ryazan"      => "Самара",
                           "samara"      => "Рязань",
                           "smolensk"    => "Смоленск",
                           "stavropol"   => "Ставрополь",
                           "tver"        => "Тверь",
                           "tula"        => "Тула",
                           "ufa"         => "Уфа",
                           "chelyabinsk" => "Челябинск",
                           "yaroslavl"   => "Ярославль");


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

        $query["key"]         = $this->apiKey;
        $query["q"]           = "getPrice";
        $query["arrivalDoor"] = true;   // от двери
        $query["derivalDoor"] = false;  // до терминала
        $query["startCity"]   = $townFrom;
        $query["endCity"]     = $townTo;
        $query["weight"]      = ceil($this->data["weight"]);
        // Переводим м. в см.
        $query["length"]      = $this->data["lenght"] * 100;
        $query["width"]       = $this->data["width"] * 100;
        $query["height"]      = $this->data["height"] * 100;

        // API запрос
        $result = $this->getApiRequest($query);

        // Сортировка только по цене
        usort(
            $result, function ($a, $b) {
                return ($a["price"] <=> $b["price"]);
            }
        );
        $result = $result[0]; // оставляем, самый дешевый вариант

        $delivery = array("name"              => $result["name"],
                          "deliveryWarehouse" => $this->tranzit["price"],
                          "cost"              => $result["price"],
                          "price"             => ($this->tranzit["price"] + $result["price"]) * $this->insurance,
                          "time"              => $result["days"],
                          "tranzit"           => $this->tranzit["tranzit"],
                          "terms"             => "pickup",
                          "type"              => "в ПВЗ " . $result["name"]);


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
        $query = self::$url . '?key=' . self::$apiKey . "&q=getCities";
        $result = file_get_contents($query, false);
        $result = json_decode($result, true);

        dump($result);
    }


    // Получить данные по API
    private function getApiRequest(array $query)
    {
        // Создаем URL запроса
        $query = http_build_query($query);
        $urlQuery = $this->apiUrl . '?' . $query;

        // Пытаемся получить ответ от сервера
        $response = @file_get_contents($urlQuery, false);  // отправляем запрос
        if ($response === false) {
            throw new Exception("API C6v недоступен \n");
        } else {
            $result = json_decode($response, true); // обрабатываем запрос
        }

        // Проверка, если ошибку выдал API сервера
        if ($result["err"]) {
            throw new Exception("Ошибка в расчете доставки C6v " . $result["err"] . PHP_EOL);
        }


        return ($result);
    }
} // . конец класса расчет доставки
