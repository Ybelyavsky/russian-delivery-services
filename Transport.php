<?php

/* Подбор типа транспорта для доставки */
// https://docs.google.com/spreadsheets/d/0B5bMqMjiLJb3MnhXS1h0cmNDQ1U/edit?resourcekey=0-aMTVuBcJrXRO-mO9pt0IzA#gid=1307770395
// Фура 2450×13500×2500 мм.

class Transport
{
    private $product;

    // Счетчик транспорта для рекурсии
    private $transport = array(
                                "truck_32" => null,
                                "truck_16" => null,
                                "bigGazel" => null,
                                "gazel"    => null,
                                "pallet_3" => null,
                                "pallet_2" => null,
                                "pallet_1" => null,
                                "sample"   => null,
                              );

    // Количество паллет, которое входит в транспорт
    private $pallets = array(
                              "truck_32" => 32,
                              "truck_16" => 16,
                              "bigGazel" => 6,
                              "gazel"    => 4,
                              "pallet_3" => 3,
                              "pallet_2" => 2,
                              "pallet_1" => 1,
                            );

    // Количество м² картона, которые входит в машины
    private $cardboard = array("E" => array(
                                              "truck_32" => 15000,
                                              "truck_16" => 7500,
                                              "bigGazel" => 2000,
                                              "gazel"    => 1100,
                                              "pallet_3" => 980,
                                              "pallet_2" => 700,
                                              "pallet_1" => 500,
                                              "sample"   => 3,
                                            ),
                               "B"  => array(
                                               "truck_32" => 15000,
                                               "truck_16" => 7500,
                                               "bigGazel" => 2000,
                                               "gazel"    => 1100,
                                               "pallet_3" => 980, // занижено специально, чтобы не было конфликта с Газелью
                                               "pallet_2" => 700,
                                               "pallet_1" => 500,
                                               "sample"   => 3,
                                             ),
                               "C"  => array(
                                               "truck_32" => 10000,
                                               "truck_16" => 5000,
                                               "bigGazel" => 1800,
                                               "gazel"    => 1100,
                                               "pallet_3" => 980, // занижено специально, чтобы не было конфликта с Газелью
                                               "pallet_2" => 500,
                                               "pallet_1" => 200,
                                               "sample"   => 3,
                                             ),
                               "BC" => array(
                                               "truck_32" => 6500,
                                               "truck_16" => 3200,
                                               "bigGazel" => 1500,
                                               "gazel"    => 1100,
                                               "pallet_3" => 580, // занижено специально, чтобы не было конфликта с Газелью
                                               "pallet_2" => 300,
                                               "pallet_1" => 150,
                                               "sample"   => 3,
                                             ),
                              );


    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    // Стартовый метод, для расчета количества транспорта
    public function calc()
    {
        // Гофрокартон и коробки
        if ($this->product->name == "box" or $this->product->name == "cardboard") {
            $this->cardboard($this->product->material["totalArea"]); // картон и коробки идут по площади
        } else {
            $this->pallets($this->product->volume["cargoType"], $this->product->volume["cargoQuantity"]); // считаем по паллетам груз
        }
        // возможно есть смысл считать уголки по обьему, но у них ограниченная высота, если только по площади пола

        $this->transport = array_filter($this->transport);  // удаляем не используемый транспорт


        return ($this->transport);
    }


    // Рекурсивная функция для перебора машин гофрокартон/коробки
    private function cardboard(float $totalArea)
    {
        // Расчет объема машина для перевозки по типам
        $transports = [];
        foreach ($this->cardboard[$this->product->material["profile"]] as $key => $value) {
            $transports[$key]["quantity"] = $totalArea / $value; // количества машин  данного типа, чтобы перевезти весь объем - не используется, для наглядности
            $transports[$key]["freeArea"] = $value - $totalArea; // свободная площадь в машине, после загрузки
        }

        // Количество типов транспорта всего
        $transportsCount = count($transports);

        // Считаем виды транспорта, в который войдет груз
        $typesTransport;  // количество типов грузовиков, в который войдет весь груз
        foreach ($transports as $key => $value) {
            // 0 вошло все под завязку или положительный остаток
            if ($value["freeArea"] >= 0) {
                $typesTransport++;
            }
        }

        // Удаляем значения с отрицательной площадью = груз не вошел полностью
        foreach ($transports as $key => $value) {
            if ($value["freeArea"] < 0) {
                unset($transports[$key]);
            }
        }

        // Если груз входит полностью в один из видов транспорта, подбираем, иначе, вычитаем фуру и рекурсия
        if ($typesTransport) {
            // Сортируем, транспорт с наименьшей свободной площадью = оптимален
            uasort(
                $transports, function ($a, $b) {
                    return ($a['freeArea'] <=> $b['freeArea']);
                }
            );

            // Вносим данные во внешний массив = счетчик
            $transportName = array_key_first($transports);
            $this->transport[$transportName]++;
        } else {
            // Если груз сразу не поеместился ни в одну из машин, добавляем фуру, остаток рекурсивно обрабатываем
            // Вычитаем фуру, т.к это наибольший транспорт, и груз больше фуры, раз даже в нее не вошло все
            $this->transport["truck_32"]++;                                                                  // добавляем фуру во внешний массив = счетчик
            $remainingArea = $totalArea - $this->cardboard[$this->product->material["profile"]]["truck_32"]; // из суммарной площади, вычитаем объем фуры
            $this->cardboard($remainingArea);                                                                // остаток площади, рекурсивно перебираем еще раз
        }
    }


    // Расчет транспорта по паллетам
    private function pallets(string $cargoType, int $cargoQuantity)
    {
        // Количество паллет/грузовых мест, если несколько коробок или пачек, то это один паллет
        if ($cargoType == "pallet") {
            $palletsQuantity = $cargoQuantity;
        } elseif ($cargoType == "box") {
            $palletsQuantity = 1;
        } elseif ($cargoType == "pack") {
            $palletsQuantity = 1;
        }


        // Расчет объема машина для перевозки по типам
        $transports = [];
        foreach ($this->pallets as $key => $value) {
            $transports[$key]["quantity"] = $palletsQuantity / $value; // количества машин  данного типа, чтобы перевезти весь объем - не используется, для наглядности
            $transports[$key]["freeArea"] = $value - $palletsQuantity; // свободная площадь в машине, после загрузки
        }

        // Количество типов транспорта всего
        $transportsCount = count($transports);

        // Считаем виды транспорта, в который войдет груз
        $typesTransport;  // количество типов грузовиков, в который войдет весь груз
        foreach ($transports as $key => $value) {
            // 0 вошло все под завязку или положительный остаток
            if ($value["freeArea"] >= 0) {
                $typesTransport++;
            }
        }

        // Удаляем значения с отрицательной площадью = груз не вошел полностью
        foreach ($transports as $key => $value) {
            if ($value["freeArea"] < 0) {
                unset($transports[$key]);
            }
        }

        // Если груз входит полностью в один из видов транспорта, подбираем, иначе, вычитаем фуру и рекурсия
        if ($typesTransport) {
            // Сортируем, транспорт с наименьшей свободной площадью = оптимален
            uasort(
                $transports, function ($a, $b) {
                    return ($a['freeArea'] <=> $b['freeArea']);
                }
            );

            // Вносим данные во внешний массив = счетчик
            $transportName = array_key_first($transports);
            $this->transport[$transportName]++;
        } else {
            // Если груз сразу не поеместился ни в одну из машин, добавляем фуру, остаток рекурсивно обрабатываем
            // Вычитаем фуру, т.к это наибольший транспорт, и груз больше фуры, раз даже в нее не вошло все
            $this->transport["truck_32"]++;                                   // добавляем фуру во внешний массив = счетчик
            $remainingPallets = $cargoQuantity - $this->pallets["truck_32"];  // из суммарной площади, вычитаем объем фуры
            $this->pallets($cargoType, $remainingPallets);                   // остаток площади, рекурсивно перебираем еще раз
        }
    }
} // . конец класса расчет транспорта
