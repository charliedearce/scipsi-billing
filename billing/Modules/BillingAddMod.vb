Imports System.Data
Imports System.Data.SqlClient
Module BillingAddMod
    Public Sub autoService()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        Try

            myCon.open()
            myCmd = New SqlCommand("SELECT * FROM tbl_scode", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)
            Dim r As DataRow
            frmBilladd.txtService.AutoCompleteCustomSource.Clear()
            For Each r In dt.Rows
                frmBilladd.txtService.AutoCompleteCustomSource.Add(r.Item(1).ToString)
            Next

        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            myCmd.Dispose()
            myCon.close()
        End Try
    End Sub

    Public Sub autoCargo()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        Try

            myCon.open()
            myCmd = New SqlCommand("SELECT * FROM tbl_tarrif", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)
            Dim r As DataRow
            frmBilladd.txtCargo.AutoCompleteCustomSource.Clear()
            For Each r In dt.Rows
                frmBilladd.txtCargo.AutoCompleteCustomSource.Add(r.Item(1).ToString)
            Next

        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            myCmd.Dispose()
            myCon.close()
        End Try
    End Sub
    Public Sub vService()
        If frmBilladd.txtService.Text = "" Then
            frmBilladd.txtService.Focus()
            frmBilling.txtNotice.Text = "Please Fill up Service Code Number."
        Else

            Try
                myCon.open()
                myCmd = New SqlCommand("SELECT * FROM tbl_scode WHERE sc_code = '" & frmBilladd.txtService.Text & "'", myCon)
                myReader = myCmd.ExecuteReader()

                If myReader.HasRows Then
                    If myReader.Read Then
                       
                        frmBilling.txtNotice.Text = ""
                    End If

                Else

                    frmBilladd.txtService.Text = ""
                    'frmBilladd.txtService.Focus()
                    frmBilling.txtNotice.Text = "Invalid Service Code number! Please Try Again."


                End If

            Catch ex As Exception

                frmBilladd.txtService.Text = ""
                frmBilladd.txtService.Focus()
                frmBilling.txtNotice.Text = "Invalid Service Code number! Please Try Again."

            Finally
                myCmd.Dispose()
                myCon.close()
            End Try
        End If
    End Sub

    Public Sub vServicetype()
        myCon.close()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)

            myCmd = New SqlCommand("SELECT * FROM tbl_service WHERE s_code = '" & frmBilladd.txtService.Text & "'", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            If myAdapter.Fill(dt) = 0 Then
                frmBilladd.txtType.Text = ""
                frmBilladd.txtRate.Text = ""
                frmBilladd.txtGross.Text = ""
            End If
            frmBilladd.cbServiceName.DataSource = dt
            frmBilladd.cbServiceName.DisplayMember = "s_desc"
            frmBilladd.cbServiceName.ValueMember = "s_type"
        Catch ex As Exception
            MsgBox(ex.Message)


        Finally
            myCmd.Dispose()
            myCon.close()
        End Try

    End Sub

    Public Sub vCargo()
        If frmBilladd.txtCargo.Text = "" Then
            frmBilladd.txtCargo.Focus()
            frmBilling.txtNotice.Text = "Please Fill up Cargo Code Number."
        Else

            Try
                myCon.open()
                myCmd = New SqlCommand("SELECT * FROM tbl_tarrif WHERE t_code = '" & frmBilladd.txtCargo.Text & "'", myCon)
                myReader = myCmd.ExecuteReader()

                If myReader.HasRows Then
                    If myReader.Read Then
                        frmBilladd.lblCargo.Text = myReader("t_desc")
                        frmBilladd.lblUnit.Text = myReader("t_unit")
                        If myReader("t_scode") = "" Then
                        Else
                            frmBilladd.cbServiceName.Text = myReader("t_sname")
                        End If

                        If frmBilladd.txtService.Text = "" Then
                            frmBilladd.txtService.Text = myReader("t_scode")
                            'frmBilladd.cbServiceName.Text = myReader("t_sname")
                        ElseIf frmBilladd.txtService.Text <> myReader("t_scode") Then
                        ElseIf frmBilladd.txtService.Text = myReader("t_scode") Then
                        End If
                        frmBilling.txtNotice.Text = ""

                        If frmBilling.cbRoute.Text = "DOMESTIC" Then

                            If frmBilladd.txtType.Text = "ARRASTRE" Then
                                frmBilladd.txtRate.Text = myReader("t_dom_arrastre")
                                frmBilladd.txtRateTemp.Text = myReader("t_dom_arrastre")
                            ElseIf frmBilladd.txtType.Text = "STEVEDORING" Then
                                frmBilladd.txtRate.Text = myReader("t_dom_stevedor")
                                frmBilladd.txtRateTemp.Text = myReader("t_dom_stevedor")
                            ElseIf frmBilladd.txtType.Text = "OTHER" Then
                                frmBilladd.txtRate.Text = myReader("t_dom_other")
                                frmBilladd.txtRateTemp.Text = myReader("t_dom_other")
                            End If
                        Else
                            If frmBilladd.txtType.Text = "ARRASTRE" Then
                                frmBilladd.txtRate.Text = myReader("t_for_arrastre")
                                frmBilladd.txtRateTemp.Text = myReader("t_for_arrastre")
                            ElseIf frmBilladd.txtType.Text = "STEVEDORING" Then
                                frmBilladd.txtRate.Text = myReader("t_for_stevedor")
                                frmBilladd.txtRateTemp.Text = myReader("t_for_stevedor")
                            ElseIf frmBilladd.txtType.Text = "OTHER" Then
                                frmBilladd.txtRate.Text = myReader("t_for_other")
                                frmBilladd.txtRateTemp.Text = myReader("t_for_other")
                            End If
                        End If


                    End If

                Else
                    frmBilladd.txtCargo.Text = ""
                    frmBilling.txtNotice.Text = "Invalid Cargo Code number! Please Try Again."
                    frmBilladd.txtCargo.Focus()

                End If

            Catch ex As Exception
                frmBilling.txtNotice.Text = "Invalid Cargo Code number! Please Try Again."
            Finally
                myCmd.Dispose()
                myCon.close()
            End Try
        End If
    End Sub

    Public Sub qtyvalidate()

        If frmBilladd.txtCargo.Text = "" Or frmBilladd.txtService.Text = "" Or frmBilladd.txtQty.Text = "" Then
        Else
            Try
               
                If frmBilladd.txtDisc.Text = "" Then
                    frmBilladd.txtDisc.Text = 0
                End If
                Dim a As Decimal = Decimal.Parse(frmBilladd.txtQty.Text)
                Dim b As Decimal = Decimal.Parse(frmBilladd.txtRate.Text)
                Dim c As Decimal = Decimal.Parse(frmBilladd.txtDisc.Text)
                Dim formula As Decimal
                formula = (a * b)
                'frmBilladd.txtGross.Text = formula
                If frmBilladd.txtRateTemp.Text <> frmBilladd.txtRate.Text Then
                Else
                    frmBilladd.txtGrosstemp.Text = formula
                End If

                If frmBilladd.txtDanger.Text = "" Or frmBilladd.txtDanger.Text = 0 Then
                    frmBilladd.txtGross.Text = frmBilladd.txtGrosstemp.Text
                    frmBilladd.txtRate.Text = frmBilladd.txtRateTemp.Text
                Else
                    frmBilladd.txtGross.Text = Decimal.Parse(frmBilladd.txtGrosstemp.Text) * Decimal.Parse(frmBilladd.txtDanger.Text)
                    frmBilladd.txtRate.Text = Decimal.Parse(frmBilladd.txtRateTemp.Text) * Decimal.Parse(frmBilladd.txtDanger.Text)
                End If
                'frmBilladd.scipsi.Text =
                frmBilling.txtNotice.Text = ""
            Catch ex As Exception
                frmBilling.txtNotice.Text = "Please Enter Valid Quantity and Try Again!"
            End Try
        End If
    End Sub

    Public Sub addItem()
        Dim danger As String = frmBilladd.txtDanger.Text
        Try
            myCon.open()
            myCmd = New SqlCommand(String.Format("INSERT INTO tbl_item_trans (it_bill_num,it_qty,it_service,it_cargo,it_unit,it_rate,it_gross,it_scode,it_ccode,it_ppa,it_net,it_tax,it_scipsi,it_charge,it_disc)VALUES('{0}','{1}','{2}','{3}','{4}','{5}','{6}', '{7}', '{8}','{9}','{10}','{11}','{12}','{13}','{14}')", frmBilling.txtBillno.Text, frmBilladd.txtQty.Text, frmBilladd.cbServiceName.Text, frmBilladd.lblCargo.Text, frmBilladd.lblUnit.Text, Convert.ToDecimal(frmBilladd.txtRate.Text), Convert.ToDecimal(frmBilladd.txtGross.Text), frmBilladd.txtService.Text, frmBilladd.txtCargo.Text, Convert.ToDecimal(frmBilladd.ppa.Text), Convert.ToDecimal(frmBilladd.net.Text), Convert.ToDecimal(frmBilladd.tax.Text), Convert.ToDecimal(frmBilladd.scipsi.Text), Convert.ToDecimal(frmBilladd.charge.Text), Convert.ToDecimal(frmBilladd.txtDisc.Text)), myCon)
            myCmd.ExecuteNonQuery()
            frmBilladd.txtQty.Focus()
            frmBilladd.txtCargo.Text = ""
            frmBilladd.txtService.Text = ""
            frmBilladd.txtQty.Text = ""
            frmBilladd.txtRate.Text = 0
            frmBilladd.txtGross.Text = 0
            frmBilladd.cbServiceName.Text = ""
            frmBilladd.lblCargo.Text = ""
            frmBilladd.lblUnit.Text = ""

            If frmBilladd.txtDanger.Text <> 0 Then
                frmBilling.txtParticulars.Text = "DANGER " + danger.Trim(".")
            Else
                frmBilling.txtParticulars.Text = ""
            End If

            frmBilladd.Dispose()

        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            myCmd.Dispose()
            myCon.close()
        End Try
    End Sub

    Public Sub viewItem()

        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT it_id,it_qty,it_unit,it_service,it_cargo,it_rate,it_disc,it_gross,it_ppa,it_net,it_tax,it_scipsi FROM tbl_item_trans WHERE it_bill_num = '" & frmBilling.txtBillno.Text & "'", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
       
        If myAdapter.Fill(dt) <> 0 Then
            With frmBilling
                .txtBillno.Enabled = False
                .btnSearch.Enabled = False
                .cbRoute.Enabled = False
                .btnDeleteItem.Enabled = True
                .btnSave.Enabled = True
                .btnOut.Enabled = False
                '.citw.Enabled = False
                .ppa.Enabled = False
                .vat.Enabled = False
            End With

        Else
            With frmBilling
                .txtDiscount.Text = 0
                .txtTotal.Text = 0
                .txtLessppa.Text = 0
                .txtNet.Text = 0
                .txtVat.Text = 0
                .txtScipsi.Text = 0
                '.txtctiw.Text = 0
                If .btnSave.Enabled = True Then
                    .txtBillno.Enabled = True
                    .cbRoute.Enabled = True

                End If
                '.citw.Enabled = True
                .ppa.Enabled = True
                .vat.Enabled = True
                .btnSearch.Enabled = True
                .btnOut.Enabled = True
                .btnDeleteItem.Enabled = False
                .btnSave.Enabled = False
            End With
        End If

        frmBilling.dgItems.DataSource = dt.DefaultView
        Dim a1 As Decimal
        Dim a2 As Decimal
        Dim a3 As Decimal
        Dim a4 As Decimal
        Dim a5 As Decimal
        Dim a6 As Decimal

            For index As Integer = 0 To frmBilling.dgItems.RowCount - 1
            With frmBilling
                a1 += Convert.ToDecimal(.dgItems.Rows(index).Cells(7).Value)
                a2 += Convert.ToDecimal(.dgItems.Rows(index).Cells(8).Value)
                a3 += Convert.ToDecimal(.dgItems.Rows(index).Cells(6).Value)
                a4 += Convert.ToDecimal(.dgItems.Rows(index).Cells(9).Value)
                a5 += Convert.ToDecimal(.dgItems.Rows(index).Cells(10).Value)
                a6 += Convert.ToDecimal(.dgItems.Rows(index).Cells(11).Value)
            End With
        Next
        With frmBilling
            .txtTotal.Text = a1
            .txtLessppa.Text = a2
            .txtDiscount.Text = a3
            .txtNet.Text = a4
            .txtVat.Text = a5
            .txtScipsi.Text = a6
        End With


        myCmd.Dispose()
        myCon.close()

        If frmBilling.dgItems.Rows.Count <= 12 Then
            frmBilling.btnAddItem.Enabled = True
        End If

    End Sub

    Public Sub computation()
        Dim ppa As Decimal
        Dim net As Decimal
        Dim tax As Decimal
        Dim scipsi As Decimal
        Dim tcharge As Decimal

        Dim exists1 As Boolean = False
        Dim percent1 As Decimal
        Dim discount1 As Decimal
        myCon.open()
        With frmBilladd

           

            myCmd = New SqlCommand("SELECT * FROM tbl_settings", myCon)
            myReader = myCmd.ExecuteReader
            If myReader.HasRows Then
                If myReader.Read Then


                    
                    For Each itm1 As DataGridViewRow In frmBilling.dgItems.Rows
                        If itm1.Cells(2).Value = "XXXX" Then
                            exists1 = True
                            If frmBilling.cbRoute.Text = "DOMESTIC" Then
                                Dim a As Decimal = myReader("dppa")
                                percent1 = itm1.Cells(5).Value / 100
                                discount1 = Math.Truncate((.txtGross.Text * ((1 - a) * percent1)) * 100) / 100
                            Else
                                Dim a As Decimal = myReader("fppa")
                                percent1 = itm1.Cells(5).Value / 100
                                discount1 = Math.Truncate((.txtGross.Text * ((1 - a) * percent1)) * 100) / 100
                            End If

                        End If
                    Next
                    


                    If frmBilling.cbRoute.Text = "DOMESTIC" Then
                        Dim dppa As Decimal = myReader("dppa")
                        Dim vat As Decimal = myReader("vat")

                        If frmBilling.ppa.Checked = True Then
                            ppa = Math.Truncate((.txtGross.Text * dppa) * 100) / 100
                        Else
                            ppa = 0
                        End If
                        net = .txtGross.Text - ppa
                        If frmBilling.vat.Checked = True Then
                            tax = Math.Truncate(((net - discount1) * vat) * 100) / 100
                        Else
                            tax = 0
                        End If
                        scipsi = Math.Truncate((net + tax) * 100) / 100
                        tcharge = Math.Truncate((tax + .txtGross.Text) * 100) / 100
                    Else
                        Dim fppa As Decimal = myReader("fppa")
                        Dim vat As Decimal = myReader("vat")
                        If frmBilling.ppa.Checked = True Then
                            ppa = Math.Truncate((.txtGross.Text * fppa) * 100) / 100
                        Else
                            ppa = 0
                        End If
                        net = .txtGross.Text - ppa
                        If frmBilling.vat.Checked = True Then
                            tax = Math.Truncate(((net - discount1) * vat) * 100) / 100
                        Else
                            tax = 0
                        End If
                        scipsi = Math.Truncate((net + tax) * 100) / 100
                        tcharge = Math.Truncate((tax + .txtGross.Text) * 100) / 100
                    End If
                End If
            End If
            Dim exists As Boolean = False
            Dim percent As Decimal
            Dim discount As Decimal
            For Each itm As DataGridViewRow In frmBilling.dgItems.Rows
                If itm.Cells(2).Value = "XXXX" Then
                    exists = True
                    If frmBilling.cbRoute.Text = "DOMESTIC" Then
                        Dim a As Decimal = myReader("dppa")
                        percent = itm.Cells(5).Value / 100
                        discount = Math.Truncate((.txtGross.Text * ((1 - a) * percent)) * 100) / 100
                    Else
                        Dim a As Decimal = myReader("fppa")
                        percent = itm.Cells(5).Value / 100
                        discount = Math.Truncate((.txtGross.Text * ((1 - a) * percent)) * 100) / 100
                    End If

                End If
            Next
            If exists = True Then
                .scipsi.Text = Math.Truncate((scipsi - discount) * 100) / 100
            Else
                .scipsi.Text = scipsi
            End If
            .ppa.Text = ppa
            .net.Text = net - discount
            .tax.Text = tax
            .txtDisc.Text = discount
            .charge.Text = tcharge
        End With
        myCon.close()
    End Sub

    Public Sub searchCustomer()

        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand(String.Format("SELECT ac_code,ac_name FROM tbl_account WHERE ac_name LIKE '%{0}%' ORDER BY ac_name DESC", SearchAcc.txtSearch.Text), myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)

        With SearchAcc
            '.dgCustomer.RowTemplate.Height = 15
            .dgCustomer.DataSource = dt.DefaultView
            .dgCustomer.Columns(0).Width = 100
            .dgCustomer.Columns(1).Width = 100
            .dgCustomer.Columns(0).SortMode = DataGridViewColumnSortMode.NotSortable
            .dgCustomer.Columns(1).SortMode = DataGridViewColumnSortMode.NotSortable
        End With
        myAdapter.Dispose()
        myCon.close()

    End Sub

    
End Module
