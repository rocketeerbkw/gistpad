
    A simple, easy to use and lightweight diff library for PHP
    by BohwaZ - http://bohwaz.net/

    Usage : see example.php

    Methods :

    simpleDiff::diff_to_array($diff, $old[, $new][, $context = true])
        will produce a php array from a traditional diff, one row by line, each row is an array :
        key => line number
        [0] => change type (simpleDiff::INS, simpleDiff::CHANGED, etc.)
        [1] => old text line
        [2] => new text line

    simpleDiff::wdiff($old, $new[, $union = ' '])
        will produce a diff based on words, like the GNU wdiff utility
        $union is the union character used between words

    simpleDiff::diff($old, $new[, $return_as_array = false)
        returns a traditional diff of two texts, like GNU diff utility

    simpleDiff::patch($old, $patch[, $return_as_array = false)
        returns a patched version of $old using the traditional diff supplied as $patch,
        like GNU path utility
