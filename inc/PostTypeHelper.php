<?php

namespace helper;

class PostTypeHelper
{
    public static function getPostTypes($output = 'names')
    {
        return \get_post_types(['_builtin' => false, 'public' => true], $output);
    }

    public static function getBuiltinPostTypes($output = 'names')
    {
        return \get_post_types(['_builtin' => true, 'public' => true], $output);
    }

    public static function getAllPostTypes($output = 'names')
    {
        return array_merge(self::getBuiltinPostTypes($output), self::getPostTypes($output));
    }

    public static function getAllRestPostTypes($output = 'names')
    {
        return array_merge(
            \get_post_types(['_builtin' => true, 'public' => true, 'show_in_rest' => true], $output),
            \get_post_types(['_builtin' => false, 'public' => true, 'show_in_rest' => true], $output)
        );
    }
}
