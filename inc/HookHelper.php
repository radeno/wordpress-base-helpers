<?php

namespace helper;

class HookHelper
{
    /**
     * Allow to remove method for an hook when, it's a class method used and class don't have variable, but you know the class name :)
     * insprired: https://github.com/herewithme/wp-filters-extras
     * More: http://wordpress.stackexchange.com/questions/57079/how-to-remove-a-filter-that-is-an-anonymous-object
     */
    public static function removeFiltersForAnonymousClass(string $hook_name, string $class_name, string $method_name, $priority = 10)
    {
        global $wp_filter;

        // Take only filters on right hook name and priority
        if (!isset($wp_filter[$hook_name][$priority]) || !is_array($wp_filter[$hook_name][$priority]))
            return false;

        // Loop on filters registered
        foreach ((array) $wp_filter[$hook_name][$priority] as $unique_id => $filter_array) {
            // Test if filter is an array ! (always for class/method)
            if (isset($filter_array['function']) && is_array($filter_array['function'])) {
                // Test if object is a class, class and method is equal to param !
                if (is_object($filter_array['function'][0]) && get_class($filter_array['function'][0]) && get_class($filter_array['function'][0]) == $class_name && $filter_array['function'][1] == $method_name) {
                    unset($wp_filter[$hook_name]->callbacks[$priority][$unique_id]);
                }
            }

        }

        return false;
    }

    public static function removeActionsForAnonymousClass(string $hook_name, string $class_name, string $method_name, $priority = 10)
    {
        self::removeFiltersForAnonymousClass($hook_name, $class_name, $method_name, $priority);
    }
}
