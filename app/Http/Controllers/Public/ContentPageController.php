<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class ContentPageController extends Controller
{
    public function suggest(): View
    {
        return view('public.content', [
            'title' => 'ข้อแนะนำก่อนเข้าร่วมอบรม',
            'description' => 'เตรียมสุขภาพ เวลา และของใช้ส่วนตัวให้พร้อมก่อนเดินทางมายังศูนย์อบรม',
        ]);
    }

    public function qualifications(): View
    {
        return view('public.content', [
            'title' => 'คุณสมบัติผู้สมัคร',
            'description' => 'ตรวจสอบอายุ ประเภทผู้สมัคร และเงื่อนไขเฉพาะของรอบหลักสูตรจากหน้ารายละเอียด',
        ]);
    }

    public function about(): View
    {
        return view('public.content', [
            'title' => 'เกี่ยวกับตโปทาราม',
            'description' => 'ข้อมูลหลักสูตร สถานที่ และช่องทางเตรียมตัวสำหรับผู้สนใจปฏิบัติธรรม',
        ]);
    }
}
