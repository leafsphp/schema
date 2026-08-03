<?php

namespace Leaf;

/**
 * Minimal stand-in for leafs/leaf's Leaf\Config container, which the
 * storage() helper from leafs/fs uses to memoize its Storage instance.
 * Only the three methods storage() touches are implemented.
 */
if (!class_exists(Config::class)) {
    class Config
    {
        protected static array $items = [];

        public static function getStatic($key)
        {
            return static::$items[$key] ?? null;
        }

        public static function singleton($key, $factory)
        {
            static::$items[$key] = $factory();
        }

        public static function get($key)
        {
            return static::$items[$key] ?? null;
        }
    }
}
