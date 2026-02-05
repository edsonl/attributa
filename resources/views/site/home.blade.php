<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresa</title>

    <!-- Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-800">

<!-- Banner Principal -->
<header class="relative bg-gradient-to-r from-purple-700 to-indigo-600 text-white">
    <div class="max-w-6xl mx-auto px-6 py-24 text-center">
        <h1 class="text-4xl md:text-5xl font-bold mb-4">Empresa</h1>
        <p class="text-lg md:text-xl text-gray-100 mb-6">
            Um lugar de fé, comunhão e transformação espiritual.
        </p>
        <a href="#sobre" class="inline-block bg-white text-purple-700 font-semibold px-6 py-3 rounded-full hover:bg-purple-100 transition">
            Conheça nossa missão
        </a>
    </div>
</header>

<!-- Bloco Sobre -->
<section id="sobre" class="max-w-6xl mx-auto px-6 py-20">
    <h2 class="text-3xl font-bold text-center text-purple-700 mb-12">
        Nossa Essência
    </h2>

    <div class="grid md:grid-cols-3 gap-10">
        <!-- Visão -->
        <div class="bg-white rounded-2xl shadow-md p-8 text-center hover:shadow-lg transition">
            <div class="text-purple-600 text-5xl mb-4">👁️</div>
            <h3 class="text-xl font-semibold mb-2">Visão</h3>
            <p class="text-gray-600">
                Ser uma comunidade que reflete o amor de Cristo e transforma vidas por meio da fé e da esperança.
            </p>
        </div>

        <!-- Comprometimento -->
        <div class="bg-white rounded-2xl shadow-md p-8 text-center hover:shadow-lg transition">
            <div class="text-purple-600 text-5xl mb-4">🤝</div>
            <h3 class="text-xl font-semibold mb-2">Comprometimento</h3>
            <p class="text-gray-600">
                Servir a Deus e ao próximo com dedicação, integridade e amor genuíno, promovendo comunhão e crescimento.
            </p>
        </div>

        <!-- Valores -->
        <div class="bg-white rounded-2xl shadow-md p-8 text-center hover:shadow-lg transition">
            <div class="text-purple-600 text-5xl mb-4">💜</div>
            <h3 class="text-xl font-semibold mb-2">Valores</h3>
            <p class="text-gray-600">
                Fé, amor, humildade e solidariedade são os pilares que sustentam nossa caminhada espiritual.
            </p>
        </div>
    </div>
</section>

<!-- Rodapé -->
<footer class="bg-purple-700 text-white py-10">
    <div class="max-w-6xl mx-auto px-6 text-center">
        <h3 class="text-xl font-semibold mb-2">Igreja Fonte de Vida</h3>
        <p class="text-gray-200 mb-4">
            © <span id="ano"></span> Todos os direitos reservados.
            <br>“Pois dele, por ele e para ele são todas as coisas.” — Romanos 11:36
        </p>
        <div class="flex justify-center gap-6 text-lg">
            <a href="#" class="hover:text-gray-300">Instagram</a>
            <a href="#" class="hover:text-gray-300">Facebook</a>
            <a href="#" class="hover:text-gray-300">YouTube</a>
        </div>
    </div>
</footer>

<script>
    // Atualiza o ano automaticamente
    document.getElementById('ano').textContent = new Date().getFullYear();
</script>
</body>
</html>
