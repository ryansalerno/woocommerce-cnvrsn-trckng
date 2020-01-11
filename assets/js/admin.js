document.addEventListener('DOMContentLoaded', function(){
	var scoped = document.querySelector('.cnvrsn-trckng');
	if (!scoped){ return; }

	decorate_rows();
	setup_toggles();

	function decorate_rows(){
		var rows = scoped.querySelectorAll('.form-table tr'),
			rows_length = rows.length;

		if (!rows_length){ return; }

		for (var cur,hdr,i = 0; i < rows_length; i++) {
			hdr = rows[i].querySelector('.integration-header');
			if (hdr){
				rows[i].classList.add('header');
				cur = hdr.querySelector('[data-toggle]').getAttribute('data-toggle');
			} else {
				rows[i].classList.add('cnvrsn-trckng-toggle-target');
				rows[i].setAttribute('data-toggler', cur);
			}
		}
	}

	function setup_toggles(){
		var toggles = scoped.querySelectorAll('[data-toggle]'),
			toggles_length = toggles.length;

		if (!toggles_length){ return; }

		for (var i = 0; i < toggles_length; i++) {
			if (!toggles[i].checked){
				hide_targets(toggles[i].getAttribute('data-toggle'));
			}

			toggles[i].addEventListener('change', function(e){
				var targets = scoped.querySelectorAll('[data-toggler='+e.target.getAttribute('data-toggle')+']'),
					targets_length = targets.length;

				if (!targets_length){ return; }

				for (var i = 0; i < targets_length; i++) {
					targets[i].style.display = e.target.checked ? '' : 'none';
				}
			});
		}
	}

	function hide_targets(target){
		var targets = scoped.querySelectorAll('[data-toggler='+target+']'),
			targets_length = targets.length;

		if (!targets_length){ return; }

		for (var i = 0; i < targets_length; i++) {
			targets[i].style.display = 'none';
		}
	}
});
