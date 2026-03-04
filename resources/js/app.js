import './bootstrap';
import Alpine from 'alpinejs';
import appState from './navigation';
import audioRecorder from './audio-recorder';

window.Alpine = Alpine;
Alpine.data('appState', appState);
Alpine.data('audioRecorder', audioRecorder);
Alpine.start();
