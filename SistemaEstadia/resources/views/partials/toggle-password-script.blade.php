<script>
    (function () {
        function fijar(boton, visible) {
            var campo = document.getElementById(boton.dataset.target);
            if (!campo) return;
            var texto = visible ? 'Ocultar contraseña' : 'Mostrar contraseña';
            campo.type = visible ? 'text' : 'password';
            boton.setAttribute('aria-pressed', String(visible));
            boton.setAttribute('aria-label', texto);
            boton.title = texto;
        }

        document.addEventListener('click', function (evento) {
            var boton = evento.target.closest('.toggle-pass');
            if (boton) fijar(boton, boton.getAttribute('aria-pressed') !== 'true');
        });

        // Al limpiar un formulario las contraseñas vuelven a quedar ocultas.
        document.addEventListener('reset', function (evento) {
            evento.target.querySelectorAll('.toggle-pass').forEach(function (boton) { fijar(boton, false); });
        });
    })();
</script>
