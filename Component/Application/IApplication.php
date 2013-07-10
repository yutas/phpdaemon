<?php
namespace Daemon\Component\Application;

interface IApplication
{
    const BASE_ON_RUN_METHOD      = 'baseOnRun';
    const BASE_RUN_METHOD         = 'baseRun';
    const BASE_ON_SHUTDOWN_METHOD = 'baseOnShutdown';

    //функция выполняется перед главным циклом
    public function onRun();

    //описывает действие, которое будет повторятся в главном цикле демона
    //когда функция вернет TRUE, процесс завершится
    public function run();

    //функция выполняется при завершении работы демона
    public function onShutdown();



    //функция-обертка для отлова исключений
    public function baseOnRun();

    //функция-обертка для отлова исключений
    public function baseRun();

    //функция-обертка для отлова исключений
    public function baseOnShutdown();
}
