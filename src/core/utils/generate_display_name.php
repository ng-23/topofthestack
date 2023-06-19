<?php

/**
 * Randomly generates a username consisting of an adjective, noun, and 1-3 digit number
 */
function generate_display_name(): String
{
    $words = json_decode(file_get_contents(realpath(dirname(__FILE__) . "../../") . "/resources/words.json"), $associative = true);
    $adjectives = $words["adjectives"];
    $nouns = $words["nouns"];
    $separators = [".", "_", "-"];

    $rand_adjective = $adjectives[array_rand($adjectives, 1)];
    $rand_noun = $nouns[array_rand($nouns, 1)];
    $rand_int = rand(0, 999);

    $camel_case = rand(0, 1);
    $username = NULL;

    if ($camel_case == 1) {
        $rand_noun = ucfirst($rand_noun);
        $username = $rand_adjective . $rand_noun . $rand_int;
    } else {
        $separator = $separators[array_rand($separators, 1)];
        $username = $rand_adjective . $separator . $rand_noun . $rand_int;
    }

    return $username;
}
