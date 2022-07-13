<?php

/**
 * Класс для расчета доставки - верхний уровень абстракции
 */


class Delivery
{
    public static function calc($f3, product $product)
    {
        // Расчетные данные по грузу
        $volume      = ($product->volume["cargoLength"] * $product->volume["cargoWidth"] * $product->volume["cargoHeight"]); // объем 1 места
        $volume      = round($volume, 2);
        $totalVolume = $volume * $product->volume["cargoQuantity"]; // объем

        $totalWeight = $product->volume["cargoWeight"] * $product->volume["cargoQuantity"]; // суммарный вес

        // Массив с данными расчета
        $data = array("from"               => "lyubertsy",
                      "to"                 => $product->region,
                      "lenght"             => $product->volume["cargoLength"],
                      "width"              => $product->volume["cargoWidth"],
                      "height"             => $product->volume["cargoHeight"],
                      "weight"             => $product->volume["cargoWeight"],
                      "pallet"             => $product->volume["cargoQuantity"],
                      "volume"             => $volume,
                      "totalVolume"        => $totalVolume,
                      "totalWeight"        => $totalWeight);

        // Подготовительные расчеты для декорации
        $transport = (new Transport($product))->calc(); // расчет какими машинами везем
        $tranzit   = new Tranzit($transport, $product); // базовый класс для декорации - расчет доставки на склад / транзит

        // Декорация транзита - конечная доставка
        if ($product->region == "moskva") {
            $delivery = (new RDelivery($transport, $tranzit, $product))->calc(); // собсвенная дсотавка РОНБЕЛ МСК и МО
        } else {
            $c6v      = (new C6v($tranzit, $data))->calc();      // агрегатор доставток через ТК C6V
            $pec      = (new Pec($tranzit, $data))->calc();      // ПЭК так же считается в C6v, расчет дублируется, на случай если C6v будет не доступен
            $baikalsr = (new Baikalsr($tranzit, $data))->calc(); // Байкал Сервис, расчет дублируется, на случай если C6v будет не доступен
            $shiptor  = (new Shiptor($tranzit, $data))->calc();  // агрегатор доставток курьерских служб Shiptor

            // Выбираем оптимальную доставку
            $deliveryMethod["c6v"]      = $c6v["price"];
            $deliveryMethod["pec"]      = $pec["price"];
            $deliveryMethod["baikalsr"] = $baikalsr["price"];
            $deliveryMethod["shiptor"]  = $shiptor["price"];

            $deliveryMethod = array_filter($deliveryMethod);                // убирем пустые значения
            asort($deliveryMethod);                                         // сортируем от меньшего к большему
            $deliveryValue = current($deliveryMethod);                      // стоимость доставки
            $deliveryName  = array_search($deliveryValue, $deliveryMethod); // ключ/название/метод способа доставки
            $delivery      = $$deliveryName;                                // оптимальная доставка
        }

        $delivery["regionName"]  = self::regionName($product);                                                                              // название региона на русском языке
        $delivery["description"] = self::description($delivery["regionName"], $delivery["tranzit"], $delivery["time"], $delivery["type"]);  // текст про доставку
        $delivery["transport"]   = $transport;                                                                                              // данные по грузовым машинам
        $delivery["advice"]      = $delivery["price"];                                                                                      // рекомендованная сумма доставки, дублируем

        // Если нет цены доставки, выводим ошибку
        if ($delivery["price"] == null) {
            throw new Exception('Доставка не посчиталась');
        }


        return ($delivery);
    }

    public static function calc2($f3, product $product)
    {
        // Подготовительные расчеты для декорации
        $transport = (new Transport($product))->calc();           // расчет какими машинами везем
        $tranzit   = (new Tranzit($transport, $product))->calc(); // расчет доставки от производителя на склад/транзит - наша машина

        // Получаем название города и региона
        $eShopLogistic = new EShopLogistic($product, $tranzit);
        $city          = $eShopLogistic->getCity();

        // Создаем результирующий массив, для последующего заполнения
        $delivery             = $city;    // данные города
        $delivery["tranzit"]  = $tranzit; // транзит на склад
        $delivery["delivery"] = [];       // место под расчеты доставки

        // Делим доставку на Москву, МО и всю Россию
        if($city["regionName"] == "Московская область" or $city["regionName"] == "Москва") {
            // Собсвенная дсотавка РОНБЕЛ, только МСК и МО
            $rDelivery = (new RDelivery2($transport, $tranzit))->calc();
            // array_push($delivery["delivery"], $rDelivery);

            // Если наша бесплатная доставка, нет смысла считать курьерские службы - скорость
            if($rDelivery["terms"] == "pickup") {
                $eShopLogistic = $eShopLogistic->calc();

                // Если доставка посчиталась добавляем
                if($eShopLogistic) {
                    $delivery["delivery"] = array_merge($delivery["delivery"], $eShopLogistic);
                }
            }
        } else {
            // Вся Россия, расчет доставки курьерскими службами
            $eShopLogistic = $eShopLogistic->calc();

            // Если доставка посчиталась добавляем
            if($eShopLogistic) {
                $delivery["delivery"] = $eShopLogistic;
            }
        }

        // Сортируем по возрастанию цены доставки
        usort(
            $delivery["delivery"], function ($a, $b) {
                return ($a['price'] <=> $b['price']);
            }
        );


        return ($delivery);
    }


    // Название города на русском
    public static function regionName(product $product)
    {
        if ($product->region == "moskva") {
            $regionName = "Москва";
        } elseif ($product->region == "spb") {
            $regionName = "Санкт-Петербург";
        } elseif ($product->region == "vladimir") {
            $regionName = "Владимир";
        } elseif ($product->region == "volgograd") {
            $regionName = "Волгоград";
        } elseif ($product->region == "voronezh") {
            $regionName = "Воронеж";
        } elseif ($product->region == "ekb") {
            $regionName = "Екатеринбург";
        } elseif ($product->region == "ivanovo") {
            $regionName = "Иваново";
        } elseif ($product->region == "kazan") {
            $regionName = "Казань";
        } elseif ($product->region == "kaluga") {
            $regionName = "Калуга";
        } elseif ($product->region == "krasnodar") {
            $regionName = "Краснодар";
        } elseif ($product->region == "nn") {
            $regionName = "Нижний Новгород";
        } elseif ($product->region == "novosibirsk") {
            $regionName = "Новосибирск";
        } elseif ($product->region == "pskov") {
            $regionName = "Псков";
        } elseif ($product->region == "rnd") {
            $regionName = "Ростов-на-Дону";
        } elseif ($product->region == "ryazan") {
            $regionName = "Рязань";
        } elseif ($product->region == "samara") {
            $regionName = "Самара";
        } elseif ($product->region == "smolensk") {
            $regionName = "Смоленск";
        } elseif ($product->region == "stavropol") {
            $regionName = "Ставрополь";
        } elseif ($product->region == "tver") {
            $regionName = "Тверь";
        } elseif ($product->region == "tula") {
            $regionName = "Тула";
        } elseif ($product->region == "ufa") {
            $regionName = "Уфа";
        } elseif ($product->region == "chelyabinsk") {
            $regionName = "Челябинск";
        } elseif ($product->region == "yaroslavl") {
            $regionName = "Ярославль";
        }

        return ($regionName);
    }


    // Текстовое описание способа доставки
    private static function description(string $regionName, int $tranzit, int $deliveryTime, $deliveryType)
    {
        if ($regionName == "Москва" and $tranzit == 1) {
            // Самовывоз Москва / Транзит склад
            $deliveryText = "<a href='https://ronbel.ru/kontakty/' target='_blank'>Самовывоз г.Люберцы</a><br>
                             <a href='https://ronbel.ru/dostavka/' target='_blank'>Стоимость доставки https://ronbel.ru/dostavka/</a>";
        } elseif ($regionName == "Москва" and $tranzit == 0) {
            // Доставка по Москве
            $deliveryText = "Бесплатная доставка по&nbsp;Москве или до&nbsp;ТК";
        } else {
            // Не Москва
            $time = "Срок доставки ~&nbsp;" . $deliveryTime . " дня<br>";
            $text = "Доставка " . $deliveryType .  " в&nbsp;" . $regionName;
            $deliveryText = $time . $text;
        }


        return ($deliveryText);
    }
}
