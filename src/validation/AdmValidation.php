<?php

namespace validation;

use Respect\Validation\Validator as v;
use validation\ArrayLength;

class AdmValidation 
{
    public static function DeckCreate() 
    {
        return [
            'deck_Name' => v::stringType()->notEmpty()->length(3, 50),
            'deck_Image' => v::stringType()->notEmpty()->length(10, null),
            'attributes' => new ArrayLength(5),
        ];
    }
}
