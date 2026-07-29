@extends('layouts.public')

@section('title', 'ค้นหาหลักสูตร | ตโปทาราม')
@section('description', 'ค้นหาหลักสูตรตามปี เดือน ประเภท และศูนย์ พร้อมลิงก์ผลลัพธ์ที่แชร์ได้')

@section('content')
    <section class="hero">
        <p class="eyebrow">หลักสูตรปฏิบัติธรรม</p>
        <h1>ค้นหาหลักสูตร</h1>
        <p class="lede">เลือกปี เดือน ประเภทหลักสูตร และศูนย์ ผลการค้นหาอยู่ใน URL นี้และส่งต่อให้ผู้อื่นได้</p>
    </section>

    @if ($search->errors !== [])
        <section class="error-summary" role="alert" aria-labelledby="filter-errors">
            <h2 id="filter-errors">ตรวจสอบตัวกรอง</h2>
            <ul>
                @foreach ($search->errors as $field => $message)
                    <li><a href="#{{ $field }}">{{ $message }}: {{ $field }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    <form class="filter-panel" method="get" action="{{ route('course-catalog.index') }}">
        <div class="filter-grid">
            <div>
                <label for="year">ปี พ.ศ.</label>
                <select id="year" name="year">
                    <option value="">ทุกปี</option>
                    @foreach ($yearOptions as $year)
                        <option value="{{ $year['value'] }}" @selected((string) $year['value'] === (string) $filters['year'])>{{ $year['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="month">เดือน</label>
                <select id="month" name="month">
                    <option value="">ทุกเดือน</option>
                    @foreach ($monthOptions as $month)
                        <option value="{{ $month['value'] }}" @selected((string) $month['value'] === (string) $filters['month'])>{{ $month['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="course_type">ประเภทหลักสูตร</label>
                <select id="course_type" name="course_type">
                    <option value="">ทุกประเภท</option>
                    @foreach ($result->courseTypes as $type)
                        <option value="{{ $type['id'] }}" @selected($type['id'] === $filters['course_type'])>{{ $type['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="center">ศูนย์</label>
                <select id="center" name="center">
                    <option value="">ทุกศูนย์</option>
                    @foreach ($result->centers as $center)
                        <option value="{{ $center['id'] }}" @selected($center['id'] === $filters['center'])>{{ $center['label'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <button type="submit">ค้นหา</button>
            <a class="secondary-action" href="{{ route('course-catalog.index') }}">ล้างตัวกรอง</a>
        </div>
    </form>

    <section class="results" aria-labelledby="result-title">
        <div class="result-heading">
            <h2 id="result-title">หลักสูตรที่พบ</h2>
            <p aria-live="polite">{{ count($result->sessions) }} รายการ</p>
        </div>

        @if ($result->sessions === [])
            <div class="empty-state">
                <h3>ไม่พบหลักสูตรตามตัวกรอง</h3>
                <p>ลองเปลี่ยนปี เดือน ประเภท หรือศูนย์ แล้วค้นหาอีกครั้ง</p>
            </div>
        @else
            <ol class="course-grid">
                @foreach ($result->sessions as $session)
                    <li class="course-card">
                        <p class="course-type">{{ $session['course_type'] }}</p>
                        <h3><a href="{{ route('course-catalog.detail', $session['code']) }}">{{ $session['title'] }}</a></h3>
                        <dl class="fact-list">
                            <div><dt>วันที่</dt><dd><time datetime="{{ $session['starts_on'] }}">{{ $displayDates[$session['code']]['starts_on'] }}</time> – <time datetime="{{ $session['ends_on'] }}">{{ $displayDates[$session['code']]['ends_on'] }}</time></dd></div>
                            <div><dt>ศูนย์</dt><dd>{{ $session['center'] }}</dd></div>
                            <div><dt>สถานะ</dt><dd>{{ ['open' => 'เปิดรับสมัคร', 'upcoming' => 'ยังไม่เปิดรับสมัคร', 'closed' => 'ปิดรับสมัครแล้ว'][$session['registration_status']] }}</dd></div>
                        </dl>
                        @if ($session['invite_only'])
                            <p class="status-badge" data-state="waiting">รับเฉพาะผู้ได้รับคำเชิญ</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
@endsection
