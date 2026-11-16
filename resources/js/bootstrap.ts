import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const initialCsrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
if (initialCsrf) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = initialCsrf;
}
