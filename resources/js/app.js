import './bootstrap';
import './sw-register';
import './offline-store';
import Alpine from 'alpinejs';
import appState from './navigation';
import audioRecorder from './audio-recorder';
import meetingLive from './meeting-live';
import onlineStatus from './online-status';

window.Alpine = Alpine;
Alpine.data('appState', appState);
Alpine.data('audioRecorder', audioRecorder);
Alpine.data('meetingLive', meetingLive);
Alpine.data('onlineStatus', onlineStatus);
Alpine.start();
