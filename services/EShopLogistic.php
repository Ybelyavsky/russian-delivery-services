<?php

/* Расчет доставки eshoplogistic.ru */
// https://docs.eshoplogistic.ru/api/#item-26
// https://dadata.ru/blog/basics/connect-suggestions/?utm_source=email&utm_medium=newsletter&utm_campaign=june22
// https://dadata.ru/suggestions/usage/address/
// https://dadata.ru/api/suggest/address/
// PHP библиотека https://github.com/hflabs/dadata-php
// Vue component https://github.com/ikloster03/vue-dadata
// Vue component https://medium.com/@fareez_ahamed/make-your-own-autocomplete-component-quickly-using-vue-js-21a642e8b140
// ФИАС-код https://fias.nalog.ru/ExtendedSearch



class EShopLogistic implements DeliveryInterface
{
    private $apiKey    = "0c740aa72fc8fb7898a114fd4afea2bd";  // API ключ
    private $apiUrl    = "https://api.eshoplogistic.ru/api/"; // Базовый URL для POST запроса

    private $fromCityCodes;    // коды города отправления по службам доставки

    private $toCityCodes;      // коды города получения по службам доставки
    private $toCityName;       // название города получения
    private $toCityRegionName; // название региона города получения

    private $deliveryServices; // сводный массив с кодами откуда-куда, для рассчетов
    private $deliveryCost;     // расчет стоимости доставки

    private $product;          // данные груза, габариты и т.п
    private $tranzit;          // стоимость транзита груза на склад

    private $insurance = 1.3;  // Запас к цене доставки на погрешность


    public function __construct(product $product, array $tranzit)
    {
        $this->product = $product; // данные груза, габариты и т.п
        $this->tranzit = $tranzit; // стоимость транзита груза на склад
    }

    // Получить название города, региона, коды служб доставки
    public function getCity()
    {
        // Получаем данные по городу получения
        $toCity = $this->getToCityCodes();

        // Заполняем внутренние свойства
        $this->toCityName       = $toCity["toCityName"];       // название города
        $this->toCityRegionName = $toCity["toCityRegionName"]; // название региона
        $this->toCityCodes      = $toCity["toCityCodes"];      // коды города


        return array(
            "cityName"   => $toCity["toCityName"],
            "regionName" => $toCity["toCityRegionName"],
            "fias"       => $this->product->region
          );
    }


    // Основной метод для расчета стоимости доставки
    public function calc()
    {
        // Коды города отправления по службам доставки
        $this->fromCityCodes = $this->getFromCityCodes();

        // Если есть доступные службы доставки считаем
        if($this->toCityCodes) {
            // Формируем массив кодов кому-куда по службам доставки, для расчета ниже
            $this->deliveryServices = $this->getDeliveryServices();

            // Расчет доставки
            $this->deliveryCost = $this->deliveryCost();

            // Результирующий массив для вывода в шаблоне
            $delivery = $this->result();
        }


        return ($delivery);
    }


    // Получить коды город отправления
    public function getFromCityCodes()
    {
        // Данные для отправки POST запроса
        $url  = $this->apiUrl . "site";
        $data = ['key' => $this->apiKey];

        // Поиск названия города
        $response = $this->getApiRequest($url, $data);

        // Коды город отаправления по службам доставки
        $fromCityCodes = $response["data"]["services"];


        return ($fromCityCodes);
    }


    // Получить коды город получения
    public function getToCityCodes()
    {
        // Данные для отправки POST запроса
        $url = $this->apiUrl . "search";

        $data = [
          'key'     => $this->apiKey,
          'target'  => $this->product->region,
          'country' => "RU"
        ];

        // Поиск названия города
        $response = $this->getApiRequest($url, $data);

        // Если город найден и коды получены
        if ($response["success"] == true) {
            // т.к запросы идут по коду ФИАС ответ может быть только один, берем первый элемент
            $toCityCodes = $response["data"][0]["services"];

            // Название города
            $toCityName = $response["data"][0]["name"];

            // Название региона
            $toCityRegionName = $response["data"][0]["region"];

            // Добавляем в текст регион
            if(!is_null($response["data"][0]["region"]) and ($toCityName != $toCityRegionName)) {
                $toCityName = $toCityName . ", " . $toCityRegionName;
            }
        }


        return array(
            "toCityName"       => $toCityName,
            "toCityRegionName" => $toCityRegionName,
            "toCityCodes"      => $toCityCodes
          );
    }


    // Получить доступные службы доставки
    public function getDeliveryServices()
    {
        $deliveryServices = [];

        // Перебираем массив to, т.к он может быть меньше, чем from
        foreach ($this->toCityCodes as $key => $value) {
            $delivery["name"] = $key;
            $delivery["from"] = $this->fromCityCodes[$key]["city_code"];
            $delivery["to"]   = $value;

            array_push($deliveryServices, $delivery);
        }


        return ($deliveryServices);
    }


    // Расчет стоимости доставки
    public function deliveryCost()
    {
        $deliveryCost = [];

        // Перебираем службы доставки по одной
        foreach ($this->deliveryServices as $value) {
            // Данные для отправки POST запроса
            $url = $this->apiUrl . "delivery/" . $value["name"];

            $data = [
              'key'        => $this->apiKey,
              'from'       => $value["from"],                         // код города-отправителя
              'to'         => $value["to"],                           // код города-получателя
              'weight'     => $this->product->volume["cargoWeight"],  // вес, кг
              'dimensions' => $this->product->volume["cargoLength"] . "*" . $this->product->volume["cargoWidth"] . "*" . $this->product->volume["cargoHeight"], // габариты груза в формате Д*Ш*В, см.
              'num'        => $this->product->volume["cargoQuantity"], // количество единиц груза
              'price'      => "1000",                                  // стоимость единицы груза, рубли
              'payment'    => "prepay"                                 // вариант оплаты: card, cash, cashless, prepay
            ];

            // Расчет доставки
            $response = $this->getApiRequest($url, $data);


            // Добавляем, если расчет прошел
            if ($response["success"] == true) {
                array_push($deliveryCost, $response["data"]);
            }
        }


        return ($deliveryCost);
    }


    // Результирующий массив для вывода в шаблоне
    private function result()
    {
        $result = []; // расчеты курьерских служб

        // Перебираем службы доставки по одной
        foreach ($this->deliveryCost as $value) {
            // По адресу
            if ($value["door"]["price"] > 0) {
                $delivery["name"]         = $value["service_name"];
                $delivery["deliveryText"] = "до двери";
                if($value["door"]["time"]) { $delivery["timeText"] = " ~ " . $value["door"]["time"];
                } // бывает, что срок доставки не указан
                $delivery["logo"]         = $value["service_logo"];
                $delivery["terms"]        = "delivery";                           // доставка по адресу
                $delivery["cost"]         = $value["door"]["price"];              // стоимость доставки курьерской службой
                $delivery["price"]        = $delivery["cost"] * $this->insurance; // цена доставки с учетом доставки на косяки

                array_push($result, $delivery);
            }

            // ПВЗ
            if ($value["terminal"]["price"] > 0) {
                $pickup["name"]         = $value["service_name"];
                $pickup["deliveryText"] = "в ПВЗ";
                if($value["terminal"]["time"]) { $pickup["timeText"] = " ~ " . $value["terminal"]["time"];
                } // бывает, что срок доставки не указан
                $pickup["time"]         = $value["terminal"]["time"];
                $pickup["logo"]         = $value["service_logo"];
                $pickup["terms"]        = "pickup";                           // самовывоз с нашего склада
                $pickup["cost"]         = $value["terminal"]["price"];        // стоимость доставки курьерской службой
                $pickup["price"]        = $pickup["cost"] * $this->insurance; // цена доставки с учетом доставки на косяки

                array_push($result, $pickup);
            }
        }


        return ($result);
    }


    // Получить данные по API
    private function getApiRequest(string $url, array $data)
    {
        // Создаем URL запроса
        $query = http_build_query($data);

        // Заголовок POST запроса
        $options = array('http' =>
                      array('method'  => 'POST',
                            'header'  => 'application/x-www-form-urlencoded',
                            'content' => $query
                          )
                        );

        // Запрос в EShopLogistic
        $context = stream_context_create($options);

        // Пытаемся получить ответ от сервера
        $response = @file_get_contents($url, false, $context); // отправляем запрос


        // Если сервер выдал ошибку
        if ($response === false) {
            // Курьерская служба не поддерживает расчет
            if($http_response_header[0] == "HTTP/1.1 422 Unprocessable Entity") {
                return (null);
            } else {
                // Просто ошибка сервера
                throw new Exception("API EShopLogistic недоступен" . PHP_EOL);
            }
        } else {
            // Обрабатываем запрос
            $result = json_decode($response, true);


            return ($result);
        }
    }
} // . конец класса расчет доставки
