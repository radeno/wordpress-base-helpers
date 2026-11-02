<?php

namespace helper;

class HookHelper
{
    /**
     * Removes a filter whose callback is a method of an object nobody kept a reference to,
     * found by the object's class name and the method name.
     * Inspired by https://github.com/herewithme/wp-filters-extras
     * More: http://wordpress.stackexchange.com/questions/57079/how-to-remove-a-filter-that-is-an-anonymous-object
     *
     * @return bool Whether a callback was removed.
     */
    public static function removeFiltersForAnonymousClass(string $hook_name, string $class_name, string $method_name, $priority = 10)
    {
        global $wp_filter;

        $removed = false;
        foreach ($wp_filter[$hook_name]->callbacks[$priority] ?? [] as $filter) {
            $callback = $filter['function'];
            if (is_array($callback) && is_object($callback[0]) && get_class($callback[0]) === $class_name && $callback[1] === $method_name) {
                // remove_filter() also keeps WP_Hook's priority list and running iterations in step.
                $removed = \remove_filter($hook_name, $callback, $priority) || $removed;
            }
        }

        return $removed;
    }

    /**
     * @return bool Whether a callback was removed.
     */
    public static function removeActionsForAnonymousClass(string $hook_name, string $class_name, string $method_name, $priority = 10)
    {
        return self::removeFiltersForAnonymousClass($hook_name, $class_name, $method_name, $priority);
    }
}
