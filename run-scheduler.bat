@echo off
REM filepath: c:\Users\AYHEM\Desktop\Back-End\Backend\scheduler.bat
SETLOCAL

IF "%1"=="start" (
    ECHO === DÉMARRAGE DU PLANIFICATEUR ===
    ECHO Démarrage en cours...
    
    REM Créer une fenêtre nommée qui sera facile à trouver
    START "Laravel-Scheduler" php artisan schedule:work
    
    ECHO Planificateur démarré dans une fenêtre séparée.
    ECHO Pour arrêter, fermez la fenêtre ou utilisez scheduler.bat stop
    EXIT /B
)

IF "%1"=="stop" (
    ECHO === ARRÊT DU PLANIFICATEUR ===
    ECHO Arrêt des processus...
    
    REM Méthode simple et fiable
    TASKKILL /F /FI "WINDOWTITLE eq Laravel-Scheduler*" 2>NUL
    
    ECHO Planificateur arrêté.
    EXIT /B
)

IF "%1"=="test" (
    ECHO === TEST DES RAPPELS ===
    php artisan tasks:send-reminders --verbose
    EXIT /B
)

ECHO Usage:
ECHO   scheduler.bat start  - Démarre le planificateur
ECHO   scheduler.bat stop   - Arrête le planificateur
ECHO   scheduler.bat test   - Teste l'envoi des rappels