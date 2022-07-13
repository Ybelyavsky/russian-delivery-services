<?php

/* Расчет доставки РОНБЕЛ */

class RDelivery implements DeliveryInterface
{
    protected $transport;
    protected $tranzit;
    protected $product;


    public function __construct(array $transport, Tranzit $tranzit, Product $product)
    {
        $this->transport = $transport;
        $this->tranzit   = $tranzit->calc(); // расчет транзита
        $this->product   = $product;
    }

    // Метод для расчета цены
    public function calc()
    {
        // Суммируем типы машин с беслплатной доставкой
        $freeDelivery = $this->transport["gazel"] + $this->transport["bigGazel"] + $this->transport["truck_16"] + $this->transport["truck_32"];

        // Тип доставки
        if ($freeDelivery > 0 and $this->product->region == "moskva") {
            $terms = "delivery";  // доставка по Москве
            $transshipment = 0;   // прямая досатвка, без перевалки на складе
        } else {
            $terms = "pickup";  // самовывоз со склада
            $transshipment = 1; // через склад
        }

        $delivery = array("name"    => $this->tranzit["name"],
                          "cost"    => $this->tranzit["cost"],
                          "price"   => $this->tranzit["price"],
                          "time"    => $this->tranzit["time"],
                          "tranzit" => $transshipment,
                          "terms"   => $terms);


        return ($delivery); // возвращаем стоимость доставки
    }
} // . конец класса расчет доставки
