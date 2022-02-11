-- SCRIPT DE CONFIGURACION DE LA BASE DE DATOS PARA LA PRACTICA 1 DE PHP

-- Ceacion de la base de datos
CREATE DATABASE practica1php; -- Falta la codificacion de caracteres
USE practica1Php;

-- Creacion tabla
DROP table users;
CREATE TABLE users (
	iduser INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mail varchar(50) unique,
    username varchar(16) unique,
    passHash varchar(60),
    userFirstName varchar(60),
    userLastName varchar(120),
    creationDate datetime,
    removeDate datetime,
    lastSignIn datetime,
    `active` TinyInt(1),
    activationDate datetime,
    activationCode char(64),
    resetPassExpiry DateTime,
	resetPassCode Char(64)
);

-- Creacion del usuario php
CREATE USER 'php'@'localhost' IDENTIFIED BY 'LaP4ssw0rToWapaNiñu';
GRANT SELECT ON Practica1Php.users TO 'php'@'localhost';
GRANT INSERT ON Practica1Php.users TO 'php'@'localhost';
GRANT UPDATE ON Practica1Php.users TO 'php'@'localhost';

