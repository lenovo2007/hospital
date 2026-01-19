# Script para sumar todas las cantidades en bolivares
def sumar_bolivares():
    # Primera sección - transacciones completadas
    primera_seccion = [
        2150, 2150, 11000, 2200, 17600, 6600, 2200, 9000, 1125, 9000,
        4400, 13500, 11000, 2200, 6750, 9200, 6900, 1380, 1150, 4600,
        1610, 4600, 4600, 1150, 5750, 4600, 2200, 9200, 23200, 4600,
        4600, 4600, 2300, 4600, 9200, 2300, 4600, 4600, 9200, 1150,
        4700, 12000, 1175, 4600, 1175, 2350, 1200, 9200, 1175, 1175,
        7350, 2300, 2400, 4900, 4900, 2400, 2400, 4800, 12250, 17500
    ]
    
    # Segunda sección - ronny
    segunda_seccion = [64500, 22800]
    
    # Tercera sección - USDT
    tercera_seccion = [
        54000, 54000, 53000, 54400, 53600, 81300, 27100, 82350, 86300
    ]
    
    # Cuarta sección - gastos varios
    cuarta_seccion = [
        2150, 2100, 6500, 3414, 1288, 15501, 911, 400, 810, 600, 220
    ]
    
    # Quinta sección - más gastos
    quinta_seccion = [
        328, 805, 3664, 1292, 991, 1500, 909, 17189
    ]
    
    # Sexta sección - gastos finales
    sexta_seccion = [2750, 1540, 2611, 1000]
    
    # Séptima sección - gastos grandes
    septima_seccion = [77500, 25000]
    
    # Calcular totales por sección
    total_primera = sum(primera_seccion)
    total_segunda = sum(segunda_seccion)
    total_tercera = sum(tercera_seccion)
    total_cuarta = sum(cuarta_seccion)
    total_quinta = sum(quinta_seccion)
    total_sexta = sum(sexta_seccion)
    total_septima = sum(septima_seccion)
    
    # Total general
    total_general = total_primera + total_segunda + total_tercera + total_cuarta + total_quinta + total_sexta + total_septima
    
    # Mostrar resultados
    print("=== RESUMEN DE SUMAS EN BOLIVARES ===")
    print(f"Primera sección (transacciones): {total_primera:,} bs")
    print(f"Segunda sección (ronny): {total_segunda:,} bs")
    print(f"Tercera sección (USDT): {total_tercera:,} bs")
    print(f"Cuarta sección (gastos joseph/varios): {total_cuarta:,} bs")
    print(f"Quinta sección (gastos varios): {total_quinta:,} bs")
    print(f"Sexta sección (gastos finales): {total_sexta:,} bs")
    print(f"Séptima sección (xbox/cosas): {total_septima:,} bs")
    print("=" * 40)
    print(f"TOTAL GENERAL: {total_general:,} bs")
    
    return total_general

if __name__ == "__main__":
    sumar_bolivares()
