<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 在測試環境中，將 gcs disk 替換為 fake disk，避免需要真實的 GCS 認證
        // 這樣可以正常測試，而不會因為 GCS 認證問題導致測試失敗
        Storage::fake('gcs');
    }
}
