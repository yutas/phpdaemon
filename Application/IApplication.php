<?php
namespace Daemon\Application;

interface IApplication
{
    //функция, которая выполняется перед главным циклом
    public function runBefore();

    //описывает действие, которое будет повторятся в главном цикле демона
    //когда функция вернет TRUE, процесс завершится
    public function run();

    //функция, которая выполняется после главного цикла
    public function runAfter();
}
