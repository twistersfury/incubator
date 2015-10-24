<?php

use Phalcon\Mvc\Model\Validator\CardNumber;

return [
    [
        CardNumber::VISA,
        4929351569693804,
        true
    ],
    [
        CardNumber::VISA,
        4485767360254767,
        true
    ],
    [
        CardNumber::VISA,
        4830875747689951,
        true
    ],
    [
        CardNumber::VISA,
        4916181688876351,
        true
    ],
    [
        CardNumber::VISA,
        1539277838295521,
        false
    ],
    [
        CardNumber::MASTERCARD,
        5489650355340390,
        true
    ],
    [
        CardNumber::MASTERCARD,
        5252467588261052,
        true
    ],
    [
        CardNumber::MASTERCARD,
        5320263975322138,
        true
    ],
    [
        CardNumber::MASTERCARD,
        5177135503698847,
        true
    ],
    [
        null,
        5177135503698847,
        true
    ],
    [
        CardNumber::MASTERCARD,
        1270338206812535,
        false
    ],
    [
        CardNumber::MASTERCARD,
        12,
        false
    ],
    [
        CardNumber::MASTERCARD,
        null,
        false
    ],
    [
        null,
        1270338206812535,
        false
    ],
    [
        CardNumber::AMERICAN_EXPRESS,
        370676121989775,
        true
    ],
    [
        CardNumber::AMERICAN_EXPRESS,
        340136100802926,
        true
    ],
    [
        CardNumber::AMERICAN_EXPRESS,
        344922644454845,
        true
    ],
    [
        CardNumber::AMERICAN_EXPRESS,
        370282036294748,
        true
    ],
    [
        CardNumber::AMERICAN_EXPRESS,
        370676121989775,
        true
    ],
];
