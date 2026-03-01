import './bootstrap';
import Alpine from 'alpinejs';
import appState from './navigation';

window.Alpine = Alpine;
Alpine.data('appState', appState);
Alpine.start();
