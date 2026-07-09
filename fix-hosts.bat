@echo off
:: Double-click this file. Windows will show a UAC prompt (click "Yes") —
:: it self-elevates and runs fix-hosts.ps1 as Administrator automatically.
powershell -NoProfile -Command "Start-Process powershell -ArgumentList '-NoProfile -ExecutionPolicy Bypass -File \"%~dp0fix-hosts.ps1\"' -Verb RunAs"
