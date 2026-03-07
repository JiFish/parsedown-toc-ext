<?php

namespace jifish\ParsedownTocExt;

if (class_exists(\ParsedownExtra::class)) {

    class ParsedownExtraToc extends \ParsedownExtra
    {
        use ParsedownTocTrait;
    }

}
