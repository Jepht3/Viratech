import 'package:flutter_test/flutter_test.dart';
import 'package:viratech/core/format.dart';

void main() {
  test('les montants sont formatés en français avec le symbole dollar', () {
    expect(money(1248.6), '1 248,60 \$');
    expect(money(0), '0,00 \$');
    expect(money(null), '0,00 \$');
    expect(money(2698, decimals: 0), '2 698 \$');
    expect(money(-22), '−22,00 \$');
  });

  test('les pourcentages sont lisibles', () {
    expect(pct(10), '10 %');
    expect(pct(7.5), '7,5 %');
  });

  test('n() convertit les nombres venant du serveur', () {
    expect(n('12.5'), 12.5);
    expect(n(null), 0);
    expect(n(3), 3);
  });
}
