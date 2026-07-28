@extends('layouts.public')

@section('title', $title.' | ตโปทาราม')
@section('description', $description)

@section('content')
    <article class="content-page">
        <p class="eyebrow">ข้อมูลสำหรับผู้สมัคร</p>
        <h1>{{ $title }}</h1>
        <p class="lede">{{ $description }}</p>
        <p><a class="primary-link" href="{{ route('course-catalog.index') }}">ค้นหาหลักสูตรที่เปิดรับสมัคร</a></p>
    </article>
@endsection
