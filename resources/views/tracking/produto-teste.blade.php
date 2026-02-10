<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Produto Teste</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Simulação de site de cliente -->
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f6f6f6;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: 6px;
        }
        .price {
            font-size: 28px;
            color: #2e7d32;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            padding: 14px 24px;
            background: #1976d2;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Produto Teste</h1>

    <p>
        Esta página simula o site de um cliente comum.
        Não utiliza Laravel, Inertia ou Vue.
    </p>

    <div class="price">
        R$ 97,00
    </div>

    <a href="#" class="btn">
        Comprar agora
    </a>

    <p style="margin-top: 40px; color: #777;">
        Página de teste para integração de tracking.
    </p>
    <a href="teste.php">Teste de link</a>
    <form action="order.php">

    </form>
</div>


<!-- Attributa Tracking -->
<script>
    (function(w,d,s,u,c){
        if (w.__ATTRIBUTA_LOADED__) return;
        w.__ATTRIBUTA_LOADED__ = true;
        var js = d.createElement(s);
        js.async = true;
        js.src = u + '?c=' + encodeURIComponent(c);
        var fjs = d.getElementsByTagName(s)[0];
        fjs.parentNode.insertBefore(js, fjs);
    })(window, document, 'script','http://attributa.cloud/api/tracking/script.js', 'CMP-GO-01KGWSGN56');
</script>
<!-- End Attributa Tracking -->

</body>
</html>
