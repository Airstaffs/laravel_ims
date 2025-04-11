// C:\xampp\htdocs\ims_laravel\resources\js\components\Sound_service.js
export const SoundService = {
    // Play sound with optional vibration
    playSound(soundName, vibrate = false) {
      const soundMap = {
        'success': window.location.origin + '/sounds/success.mp3',
        'error': window.location.origin + '/sounds/error.mp3',
        'notfound': window.location.origin + '/sounds/datanotfound.mp3',
        'successscan': window.location.origin + '/sounds/successscan.mp3',
        'scanrejected': window.location.origin + '/sounds/scanrejected.mp3',
        'alreadyScanned': window.location.origin + '/sounds/itemalreadyscanned.mp3',
         'PCNalreadyUsed': window.location.origin + '/sounds/pcnalreadyused.mp3'
      };
  
      const sound = new Audio(soundMap[soundName]);
      sound.play().catch(e => console.error('Sound playback failed:', e));
  
      // Vibrate if supported and requested
      if (vibrate && 'vibrate' in navigator) {
        // Pattern: vibrate for 200ms, pause for 100ms, vibrate for 200ms
        navigator.vibrate([200, 100, 200]);
      }
    },
  
    // Convenience methods for specific sounds
    success(vibrate = false) {
      this.playSound('success', vibrate);
    },
  
    error(vibrate = true) {
      this.playSound('error', vibrate);
    },
  
    notFound(vibrate = true) {
      this.playSound('notfound', vibrate);
    },
    
    // Special sounds for final submission
    successScan(vibrate = false) {
      this.playSound('successscan', vibrate);
    },
    
    scanRejected(vibrate = true) {
      this.playSound('scanrejected', vibrate);
    },

      alreadyScanned(vibrate = true) {
      this.playSound('alreadyScanned', vibrate);
    },

    PCNalreadyUsed(vibrate = true) {
      this.playSound('PCNalreadyUsed', vibrate);
    }

  };