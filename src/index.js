import 'jquery';

import 'bootstrap';
import 'tabler-ui/src/assets/css/dashboard.css';

import 'jquery-ui';
import 'jquery-ui/ui/widgets/sortable';
import 'jquery-ui/ui/disable-selection';
import 'jquery-ui/themes/base/all.css';

import 'select2';
import 'select2/dist/css/select2.css';
import Chart from 'chart.js';

import 'font-awesome/css/font-awesome.css';


import '../libs/jquery.clonePosition';

import Epesi from './epesi';

window.Chart = Chart;
window.EpesiClass = Epesi;

window.focus_by_id = (id) => {
  const element = document.getElementById(id);
  if (element) setTimeout(() => element.focus(), 200);
};

window.addslashes = x => x.replace(/('|"|\\)/g, '\\$1');
