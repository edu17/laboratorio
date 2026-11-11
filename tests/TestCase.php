<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * El tema del panel se compila con Vite: los tests no dependen de que exista public/build.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
