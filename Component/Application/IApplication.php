<?php
namespace Daemon\Component\Application;

interface IApplication
{
    const ON_RUN_METHOD      = 'onRun';
    const RUN_METHOD         = 'run';
    const ON_SHUTDOWN_METHOD = 'onShutdown';

    //функция выполняется перед главным циклом
    public function onRun();

    //описывает действие, которое будет повторятся в главном цикле демона
    //когда функция вернет TRUE, процесс завершится
    public function run();

    //функция выполняется при завершении работы демона
    public function onShutdown();
}
