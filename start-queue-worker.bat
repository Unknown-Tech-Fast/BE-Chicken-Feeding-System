
@echo off
title Chicken Feeding System Queue Worker

cd /d "D:\UniServer-Reload-1.3.15\www\chicken-feeding-system\Backend"

:loop
php artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=300

echo Queue worker berhenti. Restart dalam 5 detik...
timeout /t 5 /nobreak >nul
goto loop
