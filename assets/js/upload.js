document.getElementById('csvfile').onchange = function () {
  //alert('Selected file: ' + this.value);
  f = this.value;
  filename = f.replace(/.*[\/\\]/, '');
  $("p").text(filename);
};