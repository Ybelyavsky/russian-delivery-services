<?php

/* Базовый класс декоратора, цена транзита на склад */

class Tranzit implements DeliveryInterface
{
    private $product;
    private $transport;

    // Стоимость транзита паллет
    private $sample   = 650;    // образец, 1 место
    private $pallet_1 = 1700;
    private $pallet_2 = 2200;
    private $pallet_3 = 3300;

    // Стоимость машин
    private $gazel     = 6500;  // 4 поддона
    private $bigGazel  = 7500;  // 6 поддонов
    private $truck_16  = 16000; // 16 поддонов
    private $truck_32  = 32000; // 32 поддона


    public function __construct(array $transport, Product $product)
    {
        $this->transport = $transport;
        $this->product   = $product;
    }

    // Метод для расчета цены
    public function calc()
    {
        // Стоимость доставки, цепа машины * на их количество
        $price;
        foreach ($this->transport as $key => $value) {
            $price = $price + ($this->$key * $value);
        }

        // Сохраняем расчеты
        $data = array("name"    => "РОНБЕЛ",
                      "cost"    => $price,
                      "advice"  => $price,
                      "price"   => $price,
                      "time"    => 2,
                      "tranzit" => 1,
                      "terms"   => "pickup");


        return ($data);
    }
} // . конец класса расчет доставки
