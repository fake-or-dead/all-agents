@extends('layouts.public')

@section('title', 'ยังไม่มีเอกสารในระบบท้องถิ่น | ตโปทาราม')
@section('description', 'เอกสารนี้ยังไม่ได้รับอนุมัติให้นำเข้าสู่ระบบท้องถิ่น')

@section('content')
    <section class="content-page">
        <p class="eyebrow">เอกสารประกอบ</p>
        <h1>ยังไม่มีเอกสารในระบบท้องถิ่น</h1>
        <p class="lede">ลิงก์นี้คงไว้เพื่อทดสอบความเข้ากันได้ แต่ยังไม่มีไฟล์ที่ผ่านการยืนยันเจ้าของ checksum และระยะเวลาจัดเก็บ</p>
        <p><a class="primary-link" href="{{ route('course-catalog.index') }}">กลับไปหน้าหลักสูตร</a></p>
    </section>
@endsection
