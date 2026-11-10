<script lang="ts">
  /**
   * Portada pública de UTAMED (guía dm, dirección A · Noche).
   *
   * Cabecera noche con el degradado profundo y la retícula, los ocho
   * principios del modelo educativo, ayuda para entrar y el pie con el
   * isologotipo UTA y el cuadro dm. Sólo la ven visitantes: con sesión
   * iniciada, `/` redirige al inicio de cada rol.
   */
  import BotonTema from '@/components/ingreso/BotonTema.svelte';
  import LogosInstitucionales from '@/components/ingreso/LogosInstitucionales.svelte';
  import UtaMed from '@/components/ingreso/UtaMed.svelte';
  import Reticula from '@/components/student/dm/Reticula.svelte';
  import { btnNoche, enlace, etiqueta, tarjeta } from '@/lib/dm';
  import { login } from '@/routes';
  import { request } from '@/routes/password';
  import { Link } from '@inertiajs/svelte';
  import { ChevronRight, Mail } from 'lucide-svelte';

  const principios = [
    ['Asíncrono', 'Aprendizaje que se adapta a tu ritmo.'],
    ['Flexible', 'Modalidades de estudio que se ajustan a tus necesidades.'],
    ['Accesible', 'Sin barreras geográficas ni tecnológicas.'],
    ['Interactivo', 'Participación activa en tu proceso formativo.'],
    ['Retroalimentado', 'Evaluación y mejora continua de tu progreso.'],
    ['Contextualizado', 'Conocimiento aplicado a realidades profesionales.'],
    ['Centrado en el estudiante', 'Tu desarrollo integral es el eje del modelo.'],
    ['Construcción social del aprendizaje', 'Se aprende en colaboración, en comunidad.'],
  ];

  const contenedor = 'mx-auto w-full max-w-[1200px] px-4 lg:px-10';
  const tituloSeccion = 'm-0 text-[22px] leading-7 font-bold lg:text-[28px] lg:leading-[34px]';
  const enlaceSeccion =
    'inline-flex min-h-11 items-center px-3 text-sm font-semibold text-dm-noche-texto-2 no-underline hover:text-dm-noche-texto';
</script>

<svelte:head>
  <title>UTAMED | Universidad de Tarapacá</title>
</svelte:head>

<div class="vista-ingreso flex min-h-screen flex-col bg-dm-pagina font-dm text-sm leading-5 text-dm-ink">
  <section
    aria-labelledby="t-portada"
    class="sobre-noche relative overflow-hidden bg-[linear-gradient(160deg,#161949_0%,#161949_45%,#660959_100%)] text-dm-noche-texto lg:bg-[linear-gradient(135deg,#161949_0%,#161949_40%,#660959_100%)]"
  >
    <Reticula class="left-[30%] lg:left-[48%]" />

    <header
      class="relative mx-auto flex w-full max-w-[1200px] items-center gap-2 py-2.5 ps-4 pe-2 lg:flex-wrap lg:justify-between lg:gap-4 lg:px-10 lg:py-5"
    >
      <div class="flex min-w-0 flex-1 items-center gap-2 lg:flex-none lg:gap-2.5">
        <img src="/icon.png" alt="" width="32" height="32" class="size-7 shrink-0 object-contain lg:size-8" />
        <span class="font-dm-cond text-[22px] leading-[26px] font-extrabold tracking-[0.02em] lg:text-2xl lg:leading-7">UTAMED</span>
      </div>
      <nav aria-label="Secciones de la portada" class="flex items-center gap-1">
        <a href="#modelo" class="{enlaceSeccion} max-lg:hidden">Modelo educativo</a>
        <a href="#ayuda" class="{enlaceSeccion} max-lg:hidden">Ayuda</a>
        <BotonTema class="text-dm-noche-texto hover:bg-dm-noche-activo lg:ms-2" />
        <Link href={login()} class="{btnNoche} font-bold! lg:ms-2">Ingresar</Link>
      </nav>
    </header>

    <div class="relative {contenedor} flex flex-col gap-4 pt-8 pb-10 lg:gap-5 lg:pt-[72px] lg:pb-24">
      <span class="{etiqueta} text-dm-amarillo">Entorno virtual de enseñanza y aprendizaje</span>
      <h1
        id="t-portada"
        class="m-0 max-w-[640px] font-dm-cond text-[48px] leading-[46px] font-black uppercase lg:text-[72px] lg:leading-[66px]"
      >
        Bienvenido a<br />mi <UtaMed />
      </h1>
      <p class="m-0 mt-1 text-[13px] leading-[18px] text-dm-noche-texto-2">
        Escuela de Diseño e Innovación Tecnológica · Universidad de Tarapacá
      </p>
    </div>
  </section>

  <section id="modelo" aria-labelledby="t-modelo" class="border-b border-dm-linea bg-dm-tarjeta">
    <div class="{contenedor} flex flex-col gap-8 py-10 lg:py-[72px]">
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex max-w-[640px] flex-col gap-2">
          <span class="{etiqueta} text-dm-ink-3">Modelo educativo 2026</span>
          <h2 id="t-modelo" class={tituloSeccion}>Ocho principios para enseñar y aprender en línea</h2>
        </div>
        <p class="m-0 max-w-[420px] text-sm leading-5 text-dm-ink-2">
          Orientan cómo se diseñan los cursos y las actividades de la plataforma.
        </p>
      </div>
      <ol class="m-0 grid list-none grid-cols-[repeat(auto-fill,minmax(250px,1fr))] gap-x-6 gap-y-8 p-0">
        {#each principios as [titulo, texto], i (titulo)}
          <li class="flex flex-col gap-2 border-t-[3px] border-dm-ink pt-4">
            <span aria-hidden="true" class="font-dm-cond text-[32px] leading-8 font-extrabold text-dm-ink-3 tabular-nums">
              {String(i + 1).padStart(2, '0')}
            </span>
            <span class="text-lg leading-6 font-semibold">{titulo}</span>
            <span class="text-sm leading-5 text-dm-ink-2">{texto}</span>
          </li>
        {/each}
      </ol>
    </div>
  </section>

  <section id="ayuda" aria-labelledby="t-ayuda" class="{contenedor} flex flex-col gap-6 py-10 lg:py-[72px]">
    <h2 id="t-ayuda" class={tituloSeccion}>¿Problemas para entrar?</h2>
    <div class="grid grid-cols-[repeat(auto-fit,minmax(min(300px,100%),1fr))] gap-4">
      <div class="{tarjeta} flex flex-col gap-2 p-5 lg:p-6">
        <h3 class="m-0 text-lg leading-6 font-semibold">Tu RUT</h3>
        <p class="m-0 text-sm leading-5 text-dm-ink-2">
          Escríbelo sin puntos y con guion antes del dígito verificador, por ejemplo 12345678-5.
        </p>
      </div>
      <div class="{tarjeta} flex flex-col gap-2 p-5 lg:p-6">
        <h3 class="m-0 text-lg leading-6 font-semibold">Tu contraseña</h3>
        <p class="m-0 text-sm leading-5 text-dm-ink-2">
          Te llegó al correo institucional cuando se creó tu cuenta. Si no la encuentras, pide una nueva.
        </p>
        <Link href={request()} class="{enlace} self-start">
          Recuperar contraseña
          <ChevronRight size={16} aria-hidden="true" />
        </Link>
      </div>
      <div class="{tarjeta} flex flex-col gap-2 p-5 lg:p-6">
        <h3 class="m-0 text-lg leading-6 font-semibold">Soporte</h3>
        <p class="m-0 text-sm leading-5 text-dm-ink-2">
          Centro de Innovación Tecnoeducativa (CITE). Escribe desde tu correo institucional e indica tu RUT.
        </p>
        <a href="mailto:cite@gestion.uta.cl" class="{enlace} gap-1.5! self-start">
          <Mail size={16} aria-hidden="true" />
          cite@gestion.uta.cl
        </a>
      </div>
    </div>
  </section>

  <footer class="sobre-noche mt-auto bg-dm-noche text-dm-noche-texto">
    <div class="{contenedor} flex flex-col gap-6 pt-8 pb-5 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between lg:gap-8 lg:pt-10 lg:pb-8">
      <LogosInstitucionales />
      <div class="flex flex-col gap-1 text-[13px] leading-[18px] text-dm-noche-texto-2 lg:text-right">
        <span class="font-semibold text-dm-noche-texto">Universidad de Tarapacá</span>
        <span>Escuela de Diseño e Innovación Tecnológica · Diseño Multimedia</span>
        <span>Centro de Innovación Tecnoeducativa (CITE)</span>
      </div>
    </div>
    <div class={contenedor}>
      <div
        class="flex flex-col gap-1 border-t border-dm-noche-linea pt-4 pb-6 text-[13px] leading-[18px] text-dm-noche-texto-2 lg:flex-row lg:flex-wrap lg:justify-between lg:gap-2"
      >
        <span>© {new Date().getFullYear()} Universidad de Tarapacá · Arica, Chile</span>
        <span>Universidad acreditada por la CNA-Chile</span>
      </div>
    </div>
    <div aria-hidden="true" class="grid h-2 grid-cols-3">
      <span class="bg-[#DEB01F]"></span><span class="bg-[#22213F]"></span><span class="bg-[#678FCA]"></span>
    </div>
  </footer>
</div>
