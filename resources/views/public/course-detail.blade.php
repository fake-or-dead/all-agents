@extends('layouts.public')

@section('title', $course->session['title'].' | ตโปทาราม')
@section('description', $course->session['summary'])

@section('content')
    <nav aria-label="เส้นทางหน้า" class="breadcrumb">
        <a href="{{ route('course-catalog.index') }}">หลักสูตร</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">{{ $course->session['code'] }}</span>
    </nav>

    <article>
        <header class="hero">
            <p class="eyebrow">{{ $course->session['course_type'] }}</p>
            <h1>{{ $course->session['title'] }}</h1>
            <p class="lede">{{ $course->session['summary'] }}</p>
        </header>

        <section class="detail-grid" aria-label="ข้อมูลหลักสูตร">
            <div class="detail-card">
                <h2>กำหนดการและสถานที่</h2>
                <dl class="fact-list">
                    <div><dt>วันที่</dt><dd><time datetime="{{ $course->session['starts_on'] }}">{{ $displayDates['starts_on'] }}</time> – <time datetime="{{ $course->session['ends_on'] }}">{{ $displayDates['ends_on'] }}</time></dd></div>
                    <div><dt>ศูนย์</dt><dd>{{ $course->session['center']['name'] }}</dd></div>
                    <div><dt>ที่อยู่</dt><dd>{{ $course->session['center']['address'] }}</dd></div>
                    <div><dt>แผนที่</dt><dd>
                        @if ($course->session['center']['map_url'] !== null)
                            <a rel="external noopener" href="{{ $course->session['center']['map_url'] }}">เปิดแผนที่ภายนอก<span class="sr-only"> (เปิดเว็บไซต์ภายนอก)</span></a>
                        @else
                            ไม่มีลิงก์แผนที่ที่ตรวจสอบแล้ว
                        @endif
                    </dd></div>
                </dl>
            </div>
            <div class="detail-card">
                <h2>นโยบายการรับสมัคร</h2>
                <dl class="fact-list">
                    <div><dt>เปิดรับ</dt><dd><time @if ($machineDates['registration_opens_at'] !== null) datetime="{{ $machineDates['registration_opens_at'] }}" @endif>{{ $displayDates['registration_opens_at'] }}</time></dd></div>
                    <div><dt>ปิดรับ</dt><dd><time @if ($machineDates['registration_closes_at'] !== null) datetime="{{ $machineDates['registration_closes_at'] }}" @endif>{{ $displayDates['registration_closes_at'] }}</time></dd></div>
                    <div><dt>อายุ</dt><dd>{{ $course->session['minimum_age'] ?? 'ไม่กำหนด' }}–{{ $course->session['maximum_age'] ?? 'ไม่กำหนด' }} ปี</dd></div>
                    <div><dt>ประเภทผู้สมัคร</dt><dd>{{ $course->session['applicant_type'] === 'trainee' ? 'ผู้เข้าอบรม' : 'ผู้สมัครเจ้าหน้าที่' }}</dd></div>
                </dl>
            </div>
        </section>

        <section class="detail-section">
            <h2>อาจารย์ผู้สอน</h2>
            <ul class="plain-list">
                @forelse ($course->session['teachers'] as $teacher)
                    <li>{{ $teacher }}</li>
                @empty
                    <li>อยู่ระหว่างยืนยันรายชื่อ</li>
                @endforelse
            </ul>
        </section>

        <section class="detail-section">
            <h2>จำนวนที่นั่ง</h2>
            <div class="capacity-grid">
                @foreach ($course->session['capacity_rules'] as $rule)
                    <div class="capacity-card">
                        <strong>{{ ['female' => 'หญิง', 'male' => 'ชาย', 'monastic' => 'บรรพชิต'][$rule['category']] ?? $rule['category'] }}</strong>
                        @if ($rule['remaining'] >= 0)
                            <span>เหลือ {{ $rule['remaining'] }} จาก {{ $rule['capacity'] }} ที่นั่ง</span>
                        @else
                            <span>ข้อมูลจำนวนที่นั่งไม่ถูกต้อง</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="detail-section">
            <h2>เอกสารประกอบ</h2>
            <ul class="plain-list">
                @forelse ($course->session['documents'] as $document)
                    <li><a href="{{ $document['url'] }}">{{ $document['title'] }}</a> <span class="document-note">(ข้อมูลตัวอย่างภายในเครื่อง)</span></li>
                @empty
                    <li>ไม่มีเอกสารสำหรับหลักสูตรนี้</li>
                @endforelse
            </ul>
        </section>

        <section class="eligibility-panel" aria-labelledby="eligibility-title">
            <h2 id="eligibility-title">ตรวจสอบสิทธิ์เบื้องต้น</h2>
            <p>การประเมินนี้ใช้ข้อมูลที่กรอกและนโยบายรอบหลักสูตร ระบบจะตรวจสอบใบสมัครเดิมเมื่อเข้าสู่ระบบ</p>

            @if ($inputErrors !== [])
                <div class="error-summary" role="alert">
                    <h3>ตรวจสอบข้อมูล</h3>
                    <ul>
                        @foreach ($inputErrors as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="post" action="{{ route('course-catalog.eligibility', $course->session['code']) }}">
                @csrf
                <div class="filter-grid">
                    <div>
                        <label for="age">อายุ</label>
                        <input id="age" name="age" inputmode="numeric" value="{{ $eligibilityInput['age'] }}" required>
                    </div>
                    <div>
                        <label for="category">ประเภทบุคคล</label>
                        <select id="category" name="category" required>
                            <option value="">เลือกประเภท</option>
                            <option value="female" @selected($eligibilityInput['category'] === 'female')>หญิง</option>
                            <option value="male" @selected($eligibilityInput['category'] === 'male')>ชาย</option>
                            <option value="monastic" @selected($eligibilityInput['category'] === 'monastic')>บรรพชิต</option>
                        </select>
                    </div>
                    <div>
                        <label for="applicant_type">รูปแบบการสมัคร</label>
                        <select id="applicant_type" name="applicant_type" required>
                            <option value="">เลือกรูปแบบ</option>
                            <option value="trainee" @selected($eligibilityInput['applicant_type'] === 'trainee')>ผู้เข้าอบรม</option>
                            <option value="staff" @selected($eligibilityInput['applicant_type'] === 'staff')>ผู้สมัครเจ้าหน้าที่</option>
                        </select>
                    </div>
                </div>
                <button type="submit">ประเมินสิทธิ์</button>
            </form>

            <div class="eligibility-result" data-status="{{ $course->eligibility->status }}" role="status">
                <h3>{{ ['eligible' => 'ผ่านเกณฑ์เบื้องต้น', 'unavailable' => 'ยังสมัครไม่ได้', 'unknown' => 'ต้องการข้อมูลเพิ่มเติม'][$course->eligibility->status] }}</h3>
                <p>{{ $course->eligibility->message }}</p>
                <code>{{ $course->eligibility->code }}</code>
            </div>
        </section>
    </article>
@endsection
