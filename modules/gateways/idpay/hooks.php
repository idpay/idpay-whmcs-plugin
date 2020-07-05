<?php

add_hook('ShoppingCartCheckoutCompletePage', 1, function($vars) {
    $addons_html = isset($vars['addons_html']) ? $vars['addons_html'] : [];

    if (is_array($addons_html)) {
        $addons_html[] = '456';
    }

    $vars['addons_html'] = $addons_html;
    return $vars;
});