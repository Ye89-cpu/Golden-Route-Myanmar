<?php
// bootstrap/init.php

require_once __DIR__ . '/../includes/system_setting_helper.php';

if (function_exists('system_setting_bootstrap')) {
    system_setting_bootstrap();
}

/*
    AUTO SCHEDULE RUNNER

    Localhost မှာ cron job မရှိလည်း project ကို browser မှာဖွင့်တာနဲ့
    schedule_templates ထဲက active schedules တွေကိုကြည့်ပြီး
    missing trips တွေကို auto generate လုပ်ပေးမယ်။

    Default:
    - တစ်နေ့ ၁ ကြိမ်ပဲ auto run မယ်
    - နောက် 30 ရက်အတွက် trips generate မယ်
    - expired active_to template တွေကိုလည်း localhost demo အတွက် next 30 days ထုတ်ပေးမယ်
*/

require_once __DIR__ . '/../includes/auto_schedule_runner.php';

if (function_exists('grm_auto_schedule_runner')) {
    grm_auto_schedule_runner(false, 30, true);
}