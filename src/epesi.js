/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence MIT
 */

import Loader from './loader';
import axios from 'axios';
import qs from 'qs';
import ConfirmLeave from './confirmLeave';
import StatusBar from './statusBar';

class Epesi {
    loader = new Loader();
    confirmLeave = new ConfirmLeave();
    default_indicator = 'loading...';
    procOn = 0;
    client_id = 0;
    process_file = 'process.php';
    statusBar = new StatusBar();
    message = null;

    constructor(client_id, process_file_path, params = '') {
      this.client_id = client_id;
      this.process_file = process_file_path;

      axios.defaults.headers.common['X-Client-ID'] = client_id;

      jQuery(document).ajaxSend((ev, xhr, settings) => {
        xhr.setRequestHeader('X-Client-ID', this.client_id);
      });

      this.history_add(0);

      if (!params) params = '';

      window.statusbar_message = message => this.message = message;
      const statbar = document.getElementById('Base_StatusBar');
      statbar.addEventListener('click', () => { if (!this.procOn) this.statusBar.fadeOut(); });
      statbar.style.display = 'none';

      this.request(params, 0).then(() => {
        document.getElementById('epesi_loader').style.display = 'none';
        document.getElementById('main_content').style.display = '';
      });

      window.addEventListener('popstate', ({ state: { history_id } }) => this.request('', history_id));
      window._chj = this.href;
    }

    updateIndicator = () => {
      if (this.procOn) this.statusBar.fadeIn();
      else if (this.message) {
        this.statusBar.showMessage(this.message);
        this.message = null;
      } else this.statusBar.fadeOut();
    };

    updateIndicatorText = text => this.statusBar.setIndicatorText(text);

    history_add = (id) => {
      window.history.pushState({ history_id: id }, '');
    };

    request = async (url, history) => {
      this.procOn++;
      this.updateIndicator();

      let keep_focus_field = null;
      if (document.activeElement) keep_focus_field = document.activeElement.getAttribute('id');

      try {
        const response = await axios.post(this.process_file, qs.stringify({ history, url }));
        jQuery(document).trigger('e:loading');
        const func = new Function(response.data);
            window::func();
      } catch (err) {
        this.text(err.message, 'error_box', 'p');
      }

      this.procOn--;
      this.updateIndicator();
      this.append_js("jQuery(document).trigger('e:load')");
      if (keep_focus_field !== null) {
        const element = document.getElementById(keep_focus_field);
        if (element) element.focus();
      }
    };

    href = (url, indicator, mode, disableConfirmLeave = false) => {
      if (!disableConfirmLeave && !this.confirmLeave.check()) return;
      if (this.procOn === 0 || mode === 'allow') {
        !indicator ? this.updateIndicatorText(this.default_indicator) : this.updateIndicatorText(indicator);
        this.request(url);
      } else if (mode === 'queue') {
        setTimeout(() => this.href(url, indicator, mode), 500);
      }
    }

    submit_form = (formName, modulePath, indicator) => {
      this.confirmLeave.freeze(formName);
      const form = document.querySelector(`form[name="${formName}"]`);
      const submited = form.querySelector('input[name="submited"]');


      submited.value = 1;

      const formData = new FormData(form);
      const url = qs.stringify(Object.assign(formData.getAll(), { __action_module__: encodeURIComponent(modulePath) }));
      _chj(url, indicator, '');

      submited.value = 0;
    };

    text = (html, element_id, type = 'i') => {
      const element = document.getElementById(element_id);
      if (!element) return;

      switch (type) {
        case 'i':
          element.innerHTML = html;
          break;
        case 'p':
          element.insertAdjacentHTML('afterbegin', html);
          break;
        case 'a':
          element.insertAdjacentHTML('beforeend', html);
          break;
      }
    };

    load_js = (file) => {
      this.loader.load_js(file);
    };

    append_js = (texti) => {
      this.loader.execute_js(texti);
    };

    append_js_script = (texti) => {
      console.warn('DEPRECATED: use Loader.execute_js instead');
      Loader.insertScript(texti);
    };

    js_loader = () => {
      console.warn('DEPRECATED: load is invoked implicitly');
      this.loader.load();
    };

    load_css =(file) => {
      this.loader.load_css(file);
    }
}

export default Epesi;
