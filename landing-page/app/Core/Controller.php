<?php
declare(strict_types=1);

abstract class Controller
{
    protected function view(string $vista, array $datos = [], string $layout = 'main'): void
    {
        View::render($vista, $datos, $layout);
    }
}
