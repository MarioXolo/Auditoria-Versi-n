@extends('errors.layout')
@section('code', '403')
@section('title', 'Sin permiso')
@section('message', $exception->getMessage() ?: 'Tu rol no tiene acceso a esta sección.')
