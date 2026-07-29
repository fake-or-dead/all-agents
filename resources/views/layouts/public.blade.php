<!DOCTYPE html>
<html lang="th">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <meta name="description" content="{{ $__env->yieldContent('description', 'ค้นหาและตรวจสอบหลักสูตรปฏิบัติธรรมตโปทาราม') }}">
        <title>{{ $__env->yieldContent('title', 'หลักสูตรตโปทาราม') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="@yield('body-class')">
        <a class="skip-link" href="#main-content">ข้ามไปยังเนื้อหาหลัก</a>
        <header class="site-header">
            <div class="header-inner">
                <a class="wordmark" href="{{ route('course-catalog.home') }}">ตโปทาราม</a>
                <nav aria-label="เมนูหลัก">
                    <ul class="public-nav">
                        <li><a href="{{ route('course-catalog.index') }}">หลักสูตร</a></li>
                        <li><a href="{{ route('public.suggest') }}">เตรียมตัว</a></li>
                        <li><a href="{{ route('public.qualifications') }}">คุณสมบัติ</a></li>
                        <li><a href="{{ route('public.about') }}">เกี่ยวกับเรา</a></li>
                    </ul>
                </nav>
            </div>
        </header>
        <main id="main-content" class="page-shell" tabindex="-1">
            @yield('content')
        </main>
        <footer class="public-footer">
            <div class="header-inner">
                <p>ข้อมูลทดสอบภายในเครื่อง ไม่มีการเชื่อมต่อบริการภายนอก</p>
            </div>
        </footer>
    </body>
</html>
