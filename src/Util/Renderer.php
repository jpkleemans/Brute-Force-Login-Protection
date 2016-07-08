<?php

namespace BFLP\Util;

class Renderer
{
    /**
     * Render template.
     *
     * @param string $name
     * @param array $options
     *
     * @return void
     */
    public function template($name, $options)
    {
        extract($options);
        include __DIR__ . '/../../templates/' . $name . '.php';
    }

    /**
     * Render message.
     *
     * @param string $message
     * @param string $class
     *
     * @return void
     */
    public function message($message, $class = 'updated')
    {
        echo '<div class="' . $class . '"><p>' . $message . '</p></div>';
    }

    /**
     * Render error message.
     *
     * @param string $message
     *
     * @return void
     */
    public function error($message)
    {
        $this->message($message, 'error');
    }
}
