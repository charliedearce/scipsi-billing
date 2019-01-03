Imports System.Data
Imports System.Data.SqlClient
Module StatementMod
    Dim a As Date
    Dim b As Integer
    Dim c As String
    Dim d As String
    Dim e As Double
    Dim f As String
    Dim g As String

    Public Sub additemsatement()
        Try
            Dim exists As Boolean = False
            For Each itm As DataGridViewRow In frmStatement.dgStatement.Rows
                If itm.Cells(2).Value = frmStatementbilllno.txtBillnum.Text Then
                    exists = True
                End If
            Next
            Dim change As Integer = frmStatementbilllno.txtBillnum.Text
            frmStatementbilllno.txtBillnum.Text = String.Format("{0:0000000000}", change.ToString("0000000000"))
            myCon.open()
            Dim mycmd = New SqlCommand("Select * FROM tbl_bill_trans WHERE bt_status = 'FALSE' AND bt_bill_num = '" & frmStatementbilllno.txtBillnum.Text & "'", myCon)
            Dim myReader As SqlDataReader
            myReader = mycmd.ExecuteReader()

            If exists = True Then
                frmStatement.txtNotice.Text = "Bill number is on process."
                frmStatementbilllno.txtBillnum.Focus()
            Else
                If myReader.HasRows Then
                    If myReader.Read Then
                        a = myReader("bt_date")
                        b = myReader("bt_bill_num")
                        c = myReader("bt_vessel")
                        d = myReader("bt_voyage")
                        e = myReader("bt_scipsi")
                        f = myReader("bt_acc_num")
                        g = myReader("bt_type")
                    End If

                    If g = "O" Then
                        g = "(OUT)"
                    Else
                        g = "(IN)"
                    End If
                    myReader.Close()
                    mycmd.dispose()
                    If frmStatement.txtAccno.Text <> f Then
                        frmStatement.txtNotice.Text = "Account Code Not Match."
                    Else
                        Dim mycmd1 = New SqlCommand("INSERT INTO tbl_soa_item (si_billdate,si_billnum,si_vessel,si_voyage,si_service,si_scipsi,si_soanum,si_ponum) VALUES ('" & a.ToString("MM/dd/yyyy") & "','" & b & "','" & c & "','" & d & "','" & frmStatementbilllno.txtService.Text + g & "','" & e & "','" & frmStatement.txtSoano.Text & "','" & frmStatementbilllno.txtponum.Text & "')", myCon)
                        mycmd1.ExecuteNonQuery()
                        frmStatement.txtNotice.Text = ""
                        mycmd1.dispose()
                        frmStatementbilllno.Dispose()
                    End If
                Else
                    frmStatement.txtNotice.Text = "Bill number not found."
                End If
                myCon.close()
            End If

        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            myCon.close()
        End Try
    End Sub

    Public Sub ViewStatement()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)
        myCmd = New SqlCommand("SELECT * FROM tbl_soa_item WHERE si_soanum = '" & frmStatement.txtSoano.Text & "'", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        If myAdapter.Fill(dt) <> 0 Then
            'With frmOR
            '    .txtOrno.Enabled = False
            '    .btnDeleteOr.Enabled = True
            '    .btnCashtendered.Enabled = True
            '    .btnSearchOr.Enabled = False
            '    .btnBack.Enabled = False

            'End With
            'frmBilling.btnOut.Enabled = False

            With frmStatement

                If .txtindi.Text = "1" Then
                    .txtSoano.Enabled = False
                    .btnDelBill.Enabled = True
                    .btnSearchSoa.Enabled = False
                    .btnDelSoa.Enabled = False
                    .btnClose.Enabled = False
                    .btnAddBill.Enabled = False
                    If .btnNewSoa.Enabled = False Then
                        .btnAddBill.Enabled = True
                    End If
                Else

                    .btnDelSoa.Enabled = True
                    .btnDelBill.Enabled = False
                End If
            End With
        Else
            'With frmOR
            '    If .btnNewOR.Enabled = True Then
            '        .txtOrno.Enabled = False
            '    Else
            '        .txtOrno.Enabled = True
            '    End If

            '    .btnBack.Enabled = True
            '    .btnDeleteOr.Enabled = False
            '    .btnCashtendered.Enabled = False
            '    .btnSearchOr.Enabled = True
            '    frmBilling.btnOut.Enabled = True
            'End With
            With frmStatement
                If .btnNewSoa.Enabled = True Then
                    .txtSoano.Enabled = False
                Else
                    .txtSoano.Enabled = True
                End If
                .btnDelBill.Enabled = False
                .btnDelSoa.Enabled = False
                .btnSearchSoa.Enabled = True
                .btnClose.Enabled = True
            End With
        End If
        With frmStatement
            .dgStatement.DataSource = dt.DefaultView
        End With
        If frmStatement.txtindi.Text = "1" Then
            If frmStatement.dgStatement.RowCount <> 0 Then
                Dim charge As Double
                For index As Integer = 0 To frmStatement.dgStatement.RowCount - 1
                    charge += Convert.ToDouble(frmStatement.dgStatement.Rows(index).Cells(6).Value)
                Next
                frmStatement.txtCharge.Text = charge
            Else
                frmStatement.txtCharge.Text = 0
            End If
        End If
        myAdapter.Dispose()
        myCmd.Dispose()
        myCon.close()

        'If frmOR.dgOrItems.Rows.Count <= 11 Then
        '    frmOR.btnAddOr.Enabled = True
        '    ' frmOR.btnDeposit.Enabled = True
        'End If
    End Sub

    Public Sub overdue()
        On Error Resume Next
        With frmStatement
            Dim bal As Double = .txtBal.Text
            Dim charge As Double = .txtfcharge.Text
            Dim payment As Double = .txtpayment.Text
            Dim due As Double

            due = (bal + charge) - payment
            .txtOverdue.Text = due
        End With
    End Sub
    Public Sub amountdue()
        On Error Resume Next
        Dim total As Double
        With frmStatement
            total = Convert.ToDouble(.txtOverdue.Text) + Convert.ToDouble(.txtCharge.Text)
            .txtTotalamtdue.Text = total
        End With
    End Sub

    Public Sub statementacc()
        On Error Resume Next
        myCon.open()
        myCmd = New SqlCommand("SELECT * FROM tbl_account WHERE ac_code = '" & frmStatement.txtAccno.Text & "'", myCon)
        myReader = myCmd.ExecuteReader()

        If myReader.HasRows Then
            If myReader.Read Then
                frmStatement.txtCusname.Text = myReader("ac_name")
                If myReader("ac_name") = "WALK-IN" Then
                    frmStatement.txtCusname.ReadOnly = False
                    frmStatement.txtCusname.Focus()

                Else
                    frmStatement.txtCusname.ReadOnly = True

                End If
            End If
        End If
        myReader.Close()
        myCmd.Dispose()
        myCon.close()
        'Dim myCmd2 = New SqlCommand("SELECT * FROM tbl_soa_trans WHERE st_acc_code = '" & frmStatement.txtAccno.Text & "' ORDER BY id_soa DESC", myCon)
        'Dim myReader2 As SqlDataReader = myCmd2.ExecuteReader()
        'If myReader2.HasRows Then
        '    If myReader2.Read Then
        '        'frmStatement.txtBal.ReadOnly = True
        '        frmStatement.txtBal.Text = myReader2("st_amtdue")
        '    End If
        '    myReader2.Close()
        '    myCmd2.dispose()
        '    ' Else
        '    ' frmStatement.txtBal.ReadOnly = False
        '    'End If

        'Else
        '    frmStatement.txtCusname.Text = ""
        'End If
        'myCon.close()
    End Sub

    Public Sub vSoa()
        myCon.open()
        myCmd = New SqlCommand("SELECT * FROM tbl_soa_trans WHERE st_soanum = '" & frmStatement.txtSoano.Text & "'", myCon)
        myReader = myCmd.ExecuteReader()

        If myReader.HasRows Then
            frmStatement.txtNotice.Text = "S.O.A. Number is already used."
            frmStatement.btnAddBill.Enabled = False
        Else
            frmStatement.btnAddBill.Enabled = True
            frmStatement.txtNotice.Text = ""
        End If
        myReader.Close()
        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub DeleteStatementBill()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT * FROM tbl_soa_item WHERE si_soanum = '" & frmStatement.txtSoano.Text & "'", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        With frmStatementDellBill
            .dgStatementDel.DataSource = dt.DefaultView
            .dgStatementDel.Columns(0).Visible = False
            .dgStatementDel.Focus()
            On Error Resume Next
            .dgStatementDel.Rows.Item(0).Selected = True
            myCmd.Dispose()
            myCon.close()
        End With

    End Sub

    Public Sub DelStatementItem()
        myCon.open()
        myCmd = New SqlCommand("DELETE FROM tbl_soa_item WHERE id_soa_item = '" & frmStatementDellBill.txtItemid.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        frmStatement.txtNotice.Text = "Invoice Successfully Deleted!"
        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub SaveStatement()
        Try
            myCon.open()
            Dim myCmd1 = New SqlCommand("SELECT * FROM tbl_soa_trans WHERE st_soanum = '" & frmStatement.txtSoano.Text & "'", myCon)
            Dim myReader1 As SqlDataReader = myCmd1.ExecuteReader()

            If myReader1.HasRows Then
                myReader1.Close()
            Else
                myReader1.Close()
                With frmStatement
                    myCmd = New SqlCommand("INSERT INTO tbl_soa_trans (st_soanum,st_billdate,st_duedate,st_acc_code,st_customer,st_fincharge,st_payment,st_orpaid,st_overdue,st_amtdue,st_emp,st_bal,st_charge,st_address,st_to) VALUES ('" & _
                                           .txtSoano.Text & "','" & .txtBilldate.Text & "','" & .txtDuedate.Text & "','" & .txtAccno.Text & "','" & Convert.ToString(.txtCusname.Text) & "','" & Convert.ToDouble(.txtfcharge.Text) & "','" & Convert.ToDouble(.txtpayment.Text) & "','" & Convert.ToString(.txtORpaid.Text) & _
                                           "','" & Convert.ToDouble(.txtOverdue.Text) & "','" & Convert.ToDouble(.txtTotalamtdue.Text) & "','" & frmBilling.txtnametemp.Text & "','" & Convert.ToDouble(.txtBal.Text) & "','" & Convert.ToDouble(.txtCharge.Text) & "','" & .txtAddress.Text & "','" & .txtTo.Text & "')", myCon)
                End With
                myCmd.ExecuteNonQuery()
                myCmd.Dispose()
            End If
            myReader1.Close()
            myCmd1.dispose()
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        End Try

    End Sub

    Public Sub SearchSoa()

        myCon.open()
        myCmd = New SqlCommand("SELECT * FROM tbl_soa_trans WHERE st_soanum = '" & frmSearchStatement.txtSoano.Text & "'", myCon)
        myReader = myCmd.ExecuteReader()
        If myReader.HasRows Then
            If myReader.Read Then
                With frmStatement
                    .txtSoano.Text = myReader("st_soanum")
                    .txtBilldate.Text = myReader("st_billdate")
                    .txtDuedate.Text = myReader("st_duedate")
                    .txtAccno.Text = myReader("st_acc_code")
                    .txtCusname.Text = myReader("st_customer")
                    .txtfcharge.Text = myReader("st_fincharge")
                    .txtpayment.Text = myReader("st_payment")
                    .txtORpaid.Text = myReader("st_orpaid")
                    '.txtOrpaiddate.Text = myReader("st_paiddate")
                    .txtOverdue.Text = myReader("st_overdue")
                    .txtTotalamtdue.Text = myReader("st_amtdue")
                    .txtBal.Text = myReader("st_bal")
                    .txtAddress.Text = myReader("st_address")
                    .txtTo.Text = myReader("st_to")
                    .txtCharge.Text = myReader("st_charge")

                    .txtSoano.Enabled = False
                    .txtBilldate.Enabled = False
                    .txtDuedate.Enabled = False
                    .txtAccno.Enabled = False
                    .txtfcharge.Enabled = False
                    .txtpayment.Enabled = False
                    .txtORpaid.Enabled = False
                    '.txtOrpaiddate.Enabled = False
                    .txtBal.Enabled = False
                    .txtAddress.Enabled = False
                    .txtTo.Enabled = False

                    .txtindi.Text = 0
                    .btnNewSoa.Enabled = True
                    .btnedit.enabled = True
                    frmSearchStatement.Dispose()
                End With
            End If
            myCon.close()
        Else
            frmStatement.txtNotice.Text = "SOA # not found."
        End If
        myReader.Close()
        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub SoaDel()
        myCon.open()
        myCmd = New SqlCommand("DELETE FROM tbl_soa_trans WHERE st_soanum = '" & frmStatement.txtSoano.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCmd.Dispose()
        Dim myCmd1 = New SqlCommand("DELETE FROM tbl_soa_item WHERE si_soanum = '" & frmStatement.txtSoano.Text & "'", myCon)
        myCmd1.ExecuteNonQuery()
        With frmStatement
            .txtSoano.Text = ""
            .txtBilldate.Text = ""
            .txtDuedate.Text = ""
            .txtAccno.Text = ""
            .txtBal.Text = ""
            .txtfcharge.Text = ""
            .txtpayment.Text = ""
            .txtORpaid.Text = ""
            '.txtOrpaiddate.Text = ""
            .txtAddress.Text = ""
            .txtTo.Text = ""
            .txtOverdue.Text = "0.00"
            .txtCharge.Text = "0.00"
            .txtTotalamtdue.Text = "0.00"
            .txtCusname.Text = ""
            .txtNotice.Text = "SOA #" + .txtSoano.Text + " has been deleted."
        End With
        myCmd1.Dispose()
        myCon.close()
    End Sub

    Public Sub UpdateStatement()
        Try
            myCon.open()
            With frmStatement
                myCmd = New SqlCommand("UPDATE tbl_soa_trans SET st_billdate ='" & .txtBilldate.Text & "',st_duedate='" & .txtDuedate.Text & "',st_acc_code='" & .txtAccno.Text & "',st_customer='" & Convert.ToString(.txtCusname.Text) & "',st_fincharge='" & Convert.ToDouble(.txtfcharge.Text) & "',st_payment='" & Convert.ToDouble(.txtpayment.Text) & "',st_orpaid='" & Convert.ToString(.txtORpaid.Text) & _
                                       "',st_overdue='" & Convert.ToDouble(.txtOverdue.Text) & "',st_amtdue='" & Convert.ToDouble(.txtTotalamtdue.Text) & "',st_emp='" & frmBilling.txtnametemp.Text & "',st_bal='" & Convert.ToDouble(.txtBal.Text) & "',st_charge='" & Convert.ToDouble(.txtCharge.Text) & "',st_address='" & .txtAddress.Text & "',st_to='" & .txtTo.Text & "' WHERE st_soanum='" & .txtSoano.Text & "'", myCon)
            End With
            myCmd.ExecuteNonQuery()
            myCmd.Dispose()
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        End Try
    End Sub
End Module
