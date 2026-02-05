{{--
|--------------------------------------------------------------------------
| Archivos vinculados
|--------------------------------------------------------------------------
|
|  resources/sass/section/shopping-cart
|
--}}


@extends('frontend.master')

@section('title','Shopping Cart')
@section('meta-title','')
@section('meta-description','')
@section('meta-keywords','')
@section('header-css')
    @parent
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .footer hr { display: none; }
    </style>
@endsection

@section('content')
    <section class="shopping-cart newCart">
        @include('frontend.component.shopping.analytics')

        <div class="wrapper">
            <div class="font-title">
                Finalización de compra
            </div>
            {{-- {{"CarrioId: ". session()->get('carrito'); }} --}}

            <div class="content-panel">

                <div class="tab-content">

                    @include('frontend.component.shopping.payment-method.data')

                    @include('frontend.component.shopping.payment-method.register')

                </div>

                <div class="tab-content">

                    @include('frontend.component.shopping.payment-method.address')

                </div>

                <div class="tab-content">

                    @include('frontend.component.shopping.payment-method.info')

                </div>

            </div>

        </div>
    </section>
@endsection
@section('footer-scripts')
    @parent

    @session('current_user')
        <script>const current_user=true;</script>
    @endsession

    @session('section_step')
        <script>const section_step='{{ session('section_step') }}';</script>
    @endsession

    <script type="text/javascript" src="{{ mix('js/section/shopping/invoice.min.js') }}"></script>

    <script src="{{ asset('js/invoices.js') }}"></script>

    <script src="https://www.paypal.com/sdk/js?client-id={{$paypal_client_id}}&locale=es_MX&currency=MXN&components=buttons,funding-eligibility"></script>

    <script type="text/javascript" src="{{ mix('js/section/shopping/nueva-forma-pago.js') }}"></script>

@endsection
