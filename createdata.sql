CREATE OR REPLACE PROCEDURE CH.createdata IS
i_goods_id INT := 0;
i_bills_id INT := 0;
i_sales_id INT := 0;
i_bills INT := 0;
i_sales INT := 0;
i_goods INT := 0;
i_sales_count INT := 0;
i_units INT;
n_price NUMBER;
/******************************************************************************
   NAME:       createdata
   PURPOSE:    Заполнение таблиц    
******************************************************************************/
BEGIN
    DELETE FROM sales;
    DELETE FROM bills;
    DELETE FROM goods;
    DELETE FROM units;
    DELETE FROM providers;
    INSERT INTO units ( id, name) VALUES (1, 'кг');  
    INSERT INTO units ( id, name) VALUES (2, 'шт.');  
    FOR i IN 1..5 LOOP
       INSERT INTO providers ( id, name) VALUES (i, 'Поставщик ' || to_char(i));  
    END LOOP;
    FOR i IN (SELECT id FROM providers) LOOP
        FOR j IN 1..100 LOOP
            i_goods_id := i_goods_id + 1; 
            select ROUND(dbms_random.value(1,2), 0) into i_units from dual;
            select ROUND(dbms_random.value(10,1000), 0) into n_price from dual;
            INSERT INTO goods ( id, name, providers_id, units_id, price) VALUES (i_goods_id, 'Поставщик ' || to_char(i.id) || ' Товар ' || to_char(j), i.id, i_units, n_price);  
        END LOOP;
    END LOOP;
    FOR d in (select trunc(sysdate-30-1+level) d
        from dual 
        connect by level <= (sysdate-(sysdate-30))
            )  LOOP
        select ROUND(dbms_random.value(100,1000), 0) into i_bills from dual;
        FOR i IN 1..i_bills LOOP
            i_bills_id := i_bills_id + 1; 
            INSERT INTO bills ( id, dt) VALUES (i_bills_id, d.d);  
            select ROUND(dbms_random.value(1,30), 0) into i_sales from dual;
            FOR j IN 1..i_sales LOOP
                i_sales_id := i_sales_id + 1;           
                select ROUND(dbms_random.value(1,500), 0) into i_goods from dual;
                BEGIN
                select price into n_price from goods where id = i_goods;
                INSERT INTO sales ( id, bills_id, goods_id, quantity, sum) VALUES (i_sales_id, i_bills_id, i_goods, 1, n_price);
                EXCEPTION
                WHEN NO_DATA_FOUND THEN
                    NULL;
                WHEN OTHERS THEN
                    NULL;
                END;    
            END LOOP;
        END LOOP;
    END LOOP;
    COMMIT;
END createdata;
/
