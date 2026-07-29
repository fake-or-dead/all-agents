<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(LocalIdentityAccessSeeder::class);
        $this->call(CourseCatalogSeeder::class);
        $this->call(MemberBrowserFixtureSeeder::class);
    }
}
