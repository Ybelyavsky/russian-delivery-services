<?php

/* Расчет доставки РОНБЕЛ */

class RDelivery2 implements DeliveryInterface
{
    private $transport;
    private $tranzit;


    public function __construct(array $transport, array $tranzit)
    {
        $this->transport = $transport;
        $this->tranzit   = $tranzit;
    }

    // Метод для расчета цены
    public function calc()
    {
        // Суммируем типы машин с беслплатной доставкой
        $freeDelivery = $this->transport["gazel"] + $this->transport["bigGazel"] + $this->transport["truck_16"] + $this->transport["truck_32"];

        // Тип доставки
        if ($freeDelivery > 0) {
            $delivery["name"]         = "РОНБЕЛ";
            $delivery["deliveryText"] = "Бесплатная доставка по&nbsp;Москве или до&nbsp;ТК";
            $delivery["timeText"]     = "Срок доставки ~ 2-3 дня";
            $delivery["time"]         = 3;
            $delivery["logo"]         = "https://ronbel.ru/wp-content/themes/5/img/logo/new_horizontal_black_logo.webp";
            $delivery["terms"]        = "delivery";
            $delivery["delivery"]     = $this->tranzit["cost"];
            $delivery["toWarehouse"]  = 0; // прямая досатвка, без перевалки на складе
            $delivery["cost"]         = $this->tranzit["cost"];
            $delivery["price"]        = $this->tranzit["price"];
            $delivery["tranzit"]      = 0; // прямая досатвка, без перевалки на складе
        } else {
            $delivery["name"]         = "РОНБЕЛ";
            $delivery["deliveryText"] = "<a href='https://ronbel.ru/kontakty/' target='_blank'>Самовывоз г.Люберцы</a><br> <a href='https://ronbel.ru/dostavka/' target='_blank'>Стоимость доставки https://ronbel.ru/dostavka/</a>";
            $delivery["timeText"]     = "Срок доставки ~ 2-3 дня";
            $delivery["time"]         = 3;
            $delivery["logo"]         = "https://ronbel.ru/wp-content/themes/5/img/logo/new_horizontal_black_logo.webp";
            $delivery["terms"]        = "pickup";
            $delivery["delivery"]     = 0;
            $delivery["toWarehouse"]  = $this->tranzit["cost"]; // транзит на склад
            $delivery["cost"]         = $this->tranzit["cost"];
            $delivery["price"]        = $this->tranzit["price"];
            $delivery["tranzit"]      = 1; // самовывоз со склада
        }


        return ($delivery);
    }
} // . конец класса расчет доставки
