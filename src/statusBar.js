import NProgress from 'nprogress';
import 'nprogress/nprogress.css';

class StatusBar {
    indicator = 'Base_StatusBar';
    indicator_text = 'statusbar_text';

    fadeOut = () => {
      if (document.getElementById('nano-bar') !== null) NProgress.configure({ parent: '#nano-bar' });

      NProgress.start();
      const statbar = document.getElementById(this.indicator);
      jQuery(statbar).fadeOut();
      NProgress.done();
    };

    fadeIn = () => {
      document.getElementById('dismiss').style.display = 'none';
      const statbar = document.getElementById(this.indicator);
      jQuery(statbar).fadeIn();
    };

    showMessage = (message) => {
      document.getElementById('dismiss').style.display = '';
      this.setIndicatorText(message);
      setTimeout(this.fadeOut, 5000);
    };

    setIndicatorText = text => document.getElementById(this.indicator_text).innerHTML = text;
}

export default StatusBar;
