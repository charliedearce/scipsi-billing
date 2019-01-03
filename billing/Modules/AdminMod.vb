Imports System.Data
Imports System.Data.SqlClient
Imports CrystalDecisions.CrystalReports.Engine
Imports CrystalDecisions.Shared
Imports System.IO
Module AdminMod
    Dim time As String = DateTime.Now.ToString()
    Public Sub AdminbillGen()

        If frmAdmin.txtBFrom.Text = "" Or frmAdmin.txtBTo.Text = "" Then
        ElseIf frmAdmin.txtBFrom.Text > frmAdmin.txtBTo.Text Then
            MsgBox("Invalid Number Increment, Please make sure that BILL # From < To.")

        Else
            For index As Integer = frmAdmin.txtBFrom.Text To frmAdmin.txtBTo.Text
                myCon.open()
                myCmd = New SqlCommand("INSERT INTO tbl_bill_no (b_num)VALUES ('" & String.Format("{0:0000000000}", index.ToString("0000000000")) & "')", myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
            Next
            MsgBox("Bill # Successfully Generated.")

        End If
    End Sub

    Public Sub AdminorGen()
        If frmAdmin.txtOFrom.Text = "" Or frmAdmin.txtOto.Text = "" Then
        ElseIf frmAdmin.txtOFrom.Text > frmAdmin.txtOto.Text Then
            MsgBox("Invalid Number Increment, Please make sure that OR # From < To.")
        Else
            For index As Integer = frmAdmin.txtOFrom.Text To frmAdmin.txtOto.Text
                myCon.open()
                myCmd = New SqlCommand("INSERT INTO tbl_or_no (or_num)VALUES ('" & String.Format("{0:0000000000}", index.ToString("0000000000")) & "')", myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
            Next
            MsgBox("OR # Successfully Generated.")

        End If
    End Sub

    Public Sub AdmindgOrSel()
        On Error Resume Next
        Dim myindex As Integer
        Dim myor As String
        Dim myuser As String

        myindex = frmAdmin.dgOrno.CurrentRow.Index
        myor = frmAdmin.dgOrno.Item(0, myindex).Value
        myuser = frmAdmin.dgOrno.Item(1, myindex).Value

        frmAdmin.txtOrno.Text = myor
        frmAdmin.txtOrUser.Text = myuser
        frmAdmin.txtTempOr.Text = myor
    End Sub

    Public Sub AdminOrDel()

        myCon.open()
        myCmd = New SqlCommand("DELETE FROM tbl_or_no WHERE or_num = '" & frmAdmin.txtOrno.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()


    End Sub

    Public Sub AdminORview()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT or_num,or_user FROM tbl_or_no", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmAdmin.dgOrno.DataSource = dt.DefaultView


        frmAdmin.dgOrno.Columns(0).HeaderText = "OR NUMBER"
        frmAdmin.dgOrno.Columns(1).HeaderText = "OR USER"

        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub AdminOrSearch()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT or_num,or_user FROM tbl_or_no WHERE or_num LIKE '%" & frmAdmin.txtOrsearch.Text & "'", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmAdmin.dgOrno.DataSource = dt.DefaultView


        frmAdmin.dgOrno.Columns(0).HeaderText = "OR NUMBER"
        frmAdmin.dgOrno.Columns(1).HeaderText = "OR USER"

        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub AdminOrUpdate()
        myCon.open()
        myCmd = New SqlCommand("UPDATE tbl_or_no SET or_num = '" & frmAdmin.txtOrno.Text & "',or_user = '" & frmAdmin.txtOrUser.Text & "' WHERE or_num = '" & frmAdmin.txtTempOr.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub AdminBillview()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT b_num,b_user FROM tbl_bill_no", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmAdmin.dgBillno.DataSource = dt.DefaultView


        frmAdmin.dgBillno.Columns(0).HeaderText = "BILL NUMBER"
        frmAdmin.dgBillno.Columns(1).HeaderText = "BILL USER"

        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub AdmindgBillSel()
        On Error Resume Next
        Dim myindex As Integer
        Dim myor As String
        Dim myuser As String

        myindex = frmAdmin.dgBillno.CurrentRow.Index
        myor = frmAdmin.dgBillno.Item(0, myindex).Value
        myuser = frmAdmin.dgBillno.Item(1, myindex).Value

        frmAdmin.txtBillno.Text = myor
        frmAdmin.txtBilluser.Text = myuser
        frmAdmin.txtTempBill.Text = myor
    End Sub

    Public Sub AdminBillUpdate()
        myCon.open()
        myCmd = New SqlCommand("UPDATE tbl_bill_no SET b_num = '" & frmAdmin.txtBillno.Text & "',b_user = '" & frmAdmin.txtBilluser.Text & "' WHERE b_num = '" & frmAdmin.txtTempBill.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub AdminBillSearch()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT b_num,b_user FROM tbl_bill_no WHERE b_num LIKE '%" & frmAdmin.txtBillSearch.Text & "'", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmAdmin.dgBillno.DataSource = dt.DefaultView


        frmAdmin.dgBillno.Columns(0).HeaderText = "BILL NUMBER"
        frmAdmin.dgBillno.Columns(1).HeaderText = "BILL USER"

        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub AdminBillDel()

        myCon.open()
        myCmd = New SqlCommand("DELETE FROM tbl_bill_no WHERE b_num = '" & frmAdmin.txtBillno.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()


    End Sub

    Public Sub AdminUserview()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT u_uname,u_pass,u_fulname,u_role FROM tbl_users", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmAdmin.dgAdminUser.DataSource = dt.DefaultView


        frmAdmin.dgAdminUser.Columns(0).HeaderText = "USER NAME"
        frmAdmin.dgAdminUser.Columns(1).HeaderText = "PASSWORD"
        frmAdmin.dgAdminUser.Columns(2).HeaderText = "EMPLOYEE NAME"
        frmAdmin.dgAdminUser.Columns(3).HeaderText = "USER ROLE"

        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub AdmindgUserSel()
        On Error Resume Next
        Dim myindex As Integer
        Dim username As String
        Dim empname As String
        Dim userrole As String
        Dim password As String

        myindex = frmAdmin.dgAdminUser.CurrentRow.Index
        username = frmAdmin.dgAdminUser.Item(0, myindex).Value
        password = frmAdmin.dgAdminUser.Item(1, myindex).Value
        empname = frmAdmin.dgAdminUser.Item(2, myindex).Value
        userrole = frmAdmin.dgAdminUser.Item(3, myindex).Value

        frmAdmin.txtAdminUsername.Text = username
        frmAdmin.txtAdminPass.Text = password
        frmAdmin.txtAdminEmpname.Text = empname
        frmAdmin.txtTempUser.Text = username
        frmAdmin.cbAdminUserRole.Text = userrole

    End Sub

    Public Sub AdminUserDel()
        myCon.open()
        myCmd = New SqlCommand("DELETE FROM tbl_users WHERE u_uname = '" & frmAdmin.txtTempUser.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub AdminUserUpdate()
        myCon.open()
        myCmd = New SqlCommand("UPDATE tbl_users SET u_uname = '" & frmAdmin.txtAdminUsername.Text & "',u_pass = '" & frmAdmin.txtAdminPass.Text & _
                               "', u_fulname = '" & frmAdmin.txtAdminEmpname.Text & "',u_role = '" & frmAdmin.cbAdminUserRole.Text & "' WHERE u_uname = '" & frmAdmin.txtTempUser.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub AdminUserReg()
        If frmAdmin.txtRegUsername.Text = "" Or frmAdmin.txtRegPassword.Text = "" Or frmAdmin.txtRegEmpname.Text = "" Or frmAdmin.cbRegRole.Text = "" Then
            MsgBox("Please Fill up all Credentials.")
        Else

            myCon.open()
            myCmd = New SqlCommand("INSERT INTO tbl_users (u_uname,u_pass,u_fulname,u_role)VALUES ('" & frmAdmin.txtRegUsername.Text & "','" & frmAdmin.txtRegPassword.Text & "','" & frmAdmin.txtRegEmpname.Text & "','" & frmAdmin.cbRegRole.Text & "')", myCon)
            myCmd.ExecuteNonQuery()
            myCon.close()

            MsgBox("User Successfully Registered.")
            frmAdmin.txtRegUsername.Text = ""
            frmAdmin.txtRegPassword.Text = ""
            frmAdmin.txtRegEmpname.Text = ""
            frmAdmin.cbRegRole.Text = ""
        End If
    End Sub

    Public Sub AdminUserSearch()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT u_uname,u_fulname,u_role FROM tbl_users WHERE u_uname LIKE '%" & frmAdmin.txtUserSearch.Text & "%'", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmAdmin.dgAdminUser.DataSource = dt.DefaultView
        frmAdmin.dgAdminUser.Columns(0).HeaderText = "USER NAME"
        frmAdmin.dgAdminUser.Columns(1).HeaderText = "EMPLOYEE NAME"
        frmAdmin.dgAdminUser.Columns(2).HeaderText = "USER ROLE"
        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub AdminBillTransview()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT bt_bill_num, bt_account, bt_vessel, bt_voyage, bt_route, bt_type, bt_total, bt_ppa, bt_net, bt_vat, bt_scipsi, bt_date, bt_employee, bt_status FROM tbl_bill_trans", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmAdmin.dgAdminBilltrans.DataSource = dt.DefaultView


        frmAdmin.dgAdminBilltrans.Columns(0).HeaderText = "BILL #"
        frmAdmin.dgAdminBilltrans.Columns(1).HeaderText = "ACCOUNT"
        frmAdmin.dgAdminBilltrans.Columns(2).HeaderText = "VESSEL"
        frmAdmin.dgAdminBilltrans.Columns(3).HeaderText = "VOYAGE"
        frmAdmin.dgAdminBilltrans.Columns(4).HeaderText = "ROUTE"
        frmAdmin.dgAdminBilltrans.Columns(5).HeaderText = "TYPE"
        frmAdmin.dgAdminBilltrans.Columns(6).HeaderText = "TOTAL"
        frmAdmin.dgAdminBilltrans.Columns(7).HeaderText = "PPA"
        frmAdmin.dgAdminBilltrans.Columns(8).HeaderText = "NET"
        frmAdmin.dgAdminBilltrans.Columns(9).HeaderText = "VAT"
        frmAdmin.dgAdminBilltrans.Columns(10).HeaderText = "SCIPSI"
        frmAdmin.dgAdminBilltrans.Columns(11).HeaderText = "DATE"
        frmAdmin.dgAdminBilltrans.Columns(12).HeaderText = "EMPLOYEE"
        frmAdmin.dgAdminBilltrans.Columns(13).HeaderText = "STATUS"



        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub AdminBillTransSel()
        On Error Resume Next
        Dim myindex As Integer
        Dim billno As String
        Dim status As String


        myindex = frmAdmin.dgAdminBilltrans.CurrentRow.Index
        billno = frmAdmin.dgAdminBilltrans.Item(0, myindex).Value
        status = frmAdmin.dgAdminBilltrans.Item(13, myindex).Value


        frmAdmin.lblBillno.Text = billno
        frmAdmin.cbBillStatus.Text = status

    End Sub

    Public Sub AdminBillTransUpdate()
        myCon.open()
        myCmd = New SqlCommand("UPDATE tbl_bill_trans SET bt_status = '" & frmAdmin.cbBillStatus.Text & "' WHERE bt_bill_num = '" & frmAdmin.lblBillno.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub AdminBillTranSearch()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT bt_bill_num, bt_account, bt_vessel, bt_voyage, bt_route, bt_type, bt_total, bt_ppa, bt_net, bt_vat, bt_scipsi, bt_date, bt_employee, bt_status FROM tbl_bill_trans WHERE bt_bill_num LIKE '%" & frmAdmin.txtBillTransno.Text & "'", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmAdmin.dgAdminBilltrans.DataSource = dt.DefaultView


        frmAdmin.dgAdminBilltrans.Columns(0).HeaderText = "BILL #"
        frmAdmin.dgAdminBilltrans.Columns(1).HeaderText = "ACCOUNT"
        frmAdmin.dgAdminBilltrans.Columns(2).HeaderText = "VESSEL"
        frmAdmin.dgAdminBilltrans.Columns(3).HeaderText = "VOYAGE"
        frmAdmin.dgAdminBilltrans.Columns(4).HeaderText = "ROUTE"
        frmAdmin.dgAdminBilltrans.Columns(5).HeaderText = "TYPE"
        frmAdmin.dgAdminBilltrans.Columns(6).HeaderText = "TOTAL"
        frmAdmin.dgAdminBilltrans.Columns(7).HeaderText = "PPA"
        frmAdmin.dgAdminBilltrans.Columns(8).HeaderText = "NET"
        frmAdmin.dgAdminBilltrans.Columns(9).HeaderText = "VAT"
        frmAdmin.dgAdminBilltrans.Columns(10).HeaderText = "SCIPSI"
        frmAdmin.dgAdminBilltrans.Columns(11).HeaderText = "DATE"
        frmAdmin.dgAdminBilltrans.Columns(12).HeaderText = "EMPLOYEE"
        frmAdmin.dgAdminBilltrans.Columns(13).HeaderText = "STATUS"



        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub AdminOrTransview()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT or_num, or_accname, or_bill_vat, or_bill_amount, or_htax, or_net, or_date, or_status FROM tbl_or_trans", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmAdmin.dgAdminORtrans.DataSource = dt.DefaultView


        frmAdmin.dgAdminORtrans.Columns(0).HeaderText = "OR #"
        frmAdmin.dgAdminORtrans.Columns(1).HeaderText = "ACCOUNT"
        frmAdmin.dgAdminORtrans.Columns(2).HeaderText = "BILL VAT"
        frmAdmin.dgAdminORtrans.Columns(3).HeaderText = "BILL AMT."
        frmAdmin.dgAdminORtrans.Columns(4).HeaderText = "W.H.TAX"
        frmAdmin.dgAdminORtrans.Columns(5).HeaderText = "NET"
        frmAdmin.dgAdminORtrans.Columns(6).HeaderText = "DATE"
        frmAdmin.dgAdminORtrans.Columns(7).HeaderText = "STATUS"




        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub AdminOrTransSel()
        On Error Resume Next
        Dim myindex As Integer
        Dim orno As String
        Dim status As String


        myindex = frmAdmin.dgAdminORtrans.CurrentRow.Index
        orno = frmAdmin.dgAdminORtrans.Item(0, myindex).Value
        status = frmAdmin.dgAdminORtrans.Item(7, myindex).Value


        frmAdmin.lblOrTransno.Text = orno
        frmAdmin.cbOrTransStatus.Text = status

    End Sub

    Public Sub AdminOrTransUpdate()
        myCon.open()
        myCmd = New SqlCommand("UPDATE tbl_or_trans SET or_status = '" & frmAdmin.cbOrTransStatus.Text & "' WHERE or_num = '" & frmAdmin.lblOrTransno.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub AdminOrTransSearch()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT or_num, or_accname, or_bill_vat, or_bill_amount, or_htax, or_net, or_date, or_status FROM tbl_or_trans WHERE or_num LIKE '%" & frmAdmin.txtOrTransno.Text & "'", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmAdmin.dgAdminORtrans.DataSource = dt.DefaultView


        frmAdmin.dgAdminORtrans.Columns(0).HeaderText = "OR #"
        frmAdmin.dgAdminORtrans.Columns(1).HeaderText = "ACCOUNT"
        frmAdmin.dgAdminORtrans.Columns(2).HeaderText = "BILL VAT"
        frmAdmin.dgAdminORtrans.Columns(3).HeaderText = "BILL AMT."
        frmAdmin.dgAdminORtrans.Columns(4).HeaderText = "W.H.TAX"
        frmAdmin.dgAdminORtrans.Columns(5).HeaderText = "NET"
        frmAdmin.dgAdminORtrans.Columns(6).HeaderText = "DATE"
        frmAdmin.dgAdminORtrans.Columns(7).HeaderText = "STATUS"




        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub AdminAccountView()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT ac_code,ac_name FROM tbl_account", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmAdmin.dgAccounts.DataSource = dt.DefaultView

        frmAdmin.dgAccounts.Columns(0).HeaderText = "ACCOUNT CODE"
        frmAdmin.dgAccounts.Columns(1).HeaderText = "ACCOUNT NAME"

        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub AdminAccountSearch()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT ac_code,ac_name FROM tbl_account WHERE ac_code LIKE '%" & frmAdmin.txtAdminAccSearch.Text & "%'", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmAdmin.dgAccounts.DataSource = dt.DefaultView

        frmAdmin.dgAccounts.Columns(0).HeaderText = "ACCOUNT CODE"
        frmAdmin.dgAccounts.Columns(1).HeaderText = "ACCOUNT NAME"

        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub AdminAccSel()
        On Error Resume Next
        Dim myindex As Integer
        Dim code As String
        Dim accname As String


        myindex = frmAdmin.dgAccounts.CurrentRow.Index
        code = frmAdmin.dgAccounts.Item(0, myindex).Value
        accname = frmAdmin.dgAccounts.Item(1, myindex).Value


        frmAdmin.txtEditAccCode.Text = code
        frmAdmin.txtEditname.Text = accname
        frmAdmin.tempcode.Text = code

    End Sub

    Public Sub AdminAccUpdate()
        myCon.open()
        myCmd = New SqlCommand("UPDATE tbl_account SET ac_code = '" & frmAdmin.txtEditAccCode.Text & "',ac_name = '" & frmAdmin.txtEditname.Text & "' WHERE ac_code = '" & frmAdmin.tempcode.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub AdminAccDel()
        myCon.open()
        myCmd = New SqlCommand("DELETE FROM tbl_account WHERE ac_code = '" & frmAdmin.tempcode.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub AdminAccReg()
        If frmAdmin.txtAddAccountno.Text = "" Or frmAdmin.txtAddname.Text = "" Then
            MsgBox("Please Fill up all Credentials.")
        Else

            myCon.open()
            myCmd = New SqlCommand("INSERT INTO tbl_account (ac_code,ac_name)VALUES ('" & frmAdmin.txtAddAccountno.Text & "','" & frmAdmin.txtAddname.Text & "')", myCon)
            myCmd.ExecuteNonQuery()
            myCon.close()

            MsgBox("Account Successfully Registered.")
            frmAdmin.txtAddAccountno.Text = ""
            frmAdmin.txtAddname.Text = ""
        End If
    End Sub

    Public Sub mysettings()
        myCon.Open()
        myCmd = New SqlCommand("Select * From tbl_settings", myCon)
        myReader = myCmd.ExecuteReader
        If myReader.HasRows Then
            If myReader.Read = True Then
                With frmAdmin
                    .vat.Text = myReader("vat")
                    .fppa.Text = myReader("fppa")
                    .dppa.Text = myReader("dppa")
                    .whtax.Text = myReader("ctiw")
                    .disc.Text = myReader("discount")
                    .cbPrint.Text = myReader("myprint")
                End With
            End If
        End If
        myReader.Close()
        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub AdminSettUpdate()
        myCon.open()
        myCmd = New SqlCommand("UPDATE tbl_settings SET myprint = '" & frmAdmin.cbPrint.Text & "',vat = '" & frmAdmin.vat.Text & "',fppa = '" & frmAdmin.fppa.Text & "',dppa = '" & frmAdmin.dppa.Text & "',ctiw = '" & frmAdmin.whtax.Text & "',discount = '" & frmAdmin.disc.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
        MsgBox("Settings Updated")
    End Sub

    Public Sub AdminAddService()
        If frmAdmin.txtServiceDesc.Text = "" Or frmAdmin.txtServiceCode.Text = "" Then
            MsgBox("Please fill up all important fields.")
        Else
            myCon.open()
            myCmd = New SqlCommand("INSERT INTO tbl_service (s_code,s_desc,s_type)VALUES ('" & frmAdmin.txtServiceCode.Text & "','" & frmAdmin.txtServiceDesc.Text & "','" & frmAdmin.cbServiceType.Text & "')", myCon)
            myCmd.ExecuteNonQuery()
            myReader.Close()
            Dim myCmd1 = New SqlCommand("Select * From tbl_scode WHERE sc_code = '" & frmAdmin.txtServiceCode.Text & "'", myCon)
            Dim myReader1 = myCmd1.ExecuteReader
            If myReader1.HasRows Then
            Else
                myReader1.close()
                Dim myCmd2 = New SqlCommand("INSERT INTO tbl_scode (sc_code)VALUES ('" & frmAdmin.txtServiceCode.Text & "')", myCon)
                myCmd2.ExecuteNonQuery()
            End If
            myCon.close()
            frmAdmin.txtServiceDesc.Text = ""
            frmAdmin.txtServiceCode.Text = ""
        End If
    End Sub

    Public Sub AdminServiceView()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT s_code,s_desc,s_type FROM tbl_service", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmAdmin.dgAdminService.DataSource = dt.DefaultView

        frmAdmin.dgAdminService.Columns(0).HeaderText = "SERVICE CODE"
        frmAdmin.dgAdminService.Columns(1).HeaderText = "SERVICE DESCRIPTION"
        frmAdmin.dgAdminService.Columns(2).HeaderText = "SERVICE TYPE"
        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub AdminServiceSearch()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT s_code,s_desc,s_type FROM tbl_service WHERE s_desc LIKE '%" & frmAdmin.txtServiceSearch.Text & "%'", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmAdmin.dgAdminService.DataSource = dt.DefaultView

        frmAdmin.dgAdminService.Columns(0).HeaderText = "SERVICE CODE"
        frmAdmin.dgAdminService.Columns(1).HeaderText = "SERVICE DESCRIPTION"
        frmAdmin.dgAdminService.Columns(2).HeaderText = "SERVICE TYPE"
        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub AdminServiceSel()
        On Error Resume Next
        Dim myindex As Integer
        Dim code As String
        Dim sdesc As String
        Dim stype As String


        myindex = frmAdmin.dgAdminService.CurrentRow.Index
        code = frmAdmin.dgAdminService.Item(0, myindex).Value
        sdesc = frmAdmin.dgAdminService.Item(1, myindex).Value
        stype = frmAdmin.dgAdminService.Item(2, myindex).Value


        frmAdmin.txtEditServicecode.Text = code
        frmAdmin.txtEditServiceDesc.Text = sdesc
        frmAdmin.cbEditServicetype.Text = stype
        frmAdmin.Tempdesc.Text = sdesc

    End Sub

    Public Sub AdminServiceUpdate()
        myCon.open()
        myCmd = New SqlCommand("UPDATE tbl_service SET s_desc = '" & frmAdmin.txtEditServiceDesc.Text & "',s_type = '" & frmAdmin.cbEditServicetype.Text & "',s_code = '" & frmAdmin.txtEditServicecode.Text & "' WHERE s_desc = '" & frmAdmin.Tempdesc.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub AdminServiceDel()
        myCon.open()
        myCmd = New SqlCommand("DELETE FROM tbl_service WHERE s_desc = '" & frmAdmin.Tempdesc.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub AdminTarrifView()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT t_code,t_desc,t_basis,t_unit,t_class,t_dom_arrastre,t_dom_stevedor,t_dom_other,t_for_arrastre,t_for_stevedor,t_for_other,t_scode,t_sname FROM tbl_tarrif", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmAdmin.dgTarrif.DataSource = dt.DefaultView

        frmAdmin.dgTarrif.Columns(0).HeaderText = "CODE"
        frmAdmin.dgTarrif.Columns(1).HeaderText = "DESCRIPTION"
        frmAdmin.dgTarrif.Columns(2).HeaderText = "BASIS"

        frmAdmin.dgTarrif.Columns(3).HeaderText = "UNIT"
        frmAdmin.dgTarrif.Columns(4).HeaderText = "CLASS"
        frmAdmin.dgTarrif.Columns(5).HeaderText = "DOM. ARRASTRE"

        frmAdmin.dgTarrif.Columns(6).HeaderText = "DOM. STEVEDOR"
        frmAdmin.dgTarrif.Columns(7).HeaderText = "DOM. OTHER"
        frmAdmin.dgTarrif.Columns(8).HeaderText = "FOR. ARRASTRE"

        frmAdmin.dgTarrif.Columns(9).HeaderText = "FOR. STEVEDOR"
        frmAdmin.dgTarrif.Columns(10).HeaderText = "FOR. STEVEDOR"
        frmAdmin.dgTarrif.Columns(11).HeaderText = "SERVICE CODE"
        frmAdmin.dgTarrif.Columns(12).HeaderText = "ALIAS"
        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub AdminTarrifSearch()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT t_code,t_desc,t_basis,t_unit,t_class,t_dom_arrastre,t_dom_stevedor,t_dom_other,t_for_arrastre,t_for_stevedor,t_for_other,t_scode FROM tbl_tarrif WHERE t_code LIKE '%" & frmAdmin.txtTarrifSearch.Text & "%'", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmAdmin.dgTarrif.DataSource = dt.DefaultView

        frmAdmin.dgTarrif.Columns(0).HeaderText = "CODE"
        frmAdmin.dgTarrif.Columns(1).HeaderText = "DESCRIPTION"
        frmAdmin.dgTarrif.Columns(2).HeaderText = "BASIS"

        frmAdmin.dgTarrif.Columns(3).HeaderText = "UNIT"
        frmAdmin.dgTarrif.Columns(4).HeaderText = "CLASS"
        frmAdmin.dgTarrif.Columns(5).HeaderText = "DOM. ARRASTRE"

        frmAdmin.dgTarrif.Columns(6).HeaderText = "DOM. STEVEDOR"
        frmAdmin.dgTarrif.Columns(7).HeaderText = "DOM. OTHER"
        frmAdmin.dgTarrif.Columns(8).HeaderText = "FOR. ARRASTRE"

        frmAdmin.dgTarrif.Columns(9).HeaderText = "FOR. STEVEDOR"
        frmAdmin.dgTarrif.Columns(10).HeaderText = "FOR. STEVEDOR"
        frmAdmin.dgTarrif.Columns(11).HeaderText = "SERVICE CODE"
        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub servicecode()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)

            myCmd = New SqlCommand("SELECT * FROM tbl_scode", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)

            frmAdmin.cbTarrifServiceCode.DataSource = dt
            frmAdmin.cbTarrifServiceCode.DisplayMember = "sc_code"
        Catch ex As Exception
            MsgBox(ex.Message)


        Finally
            myCmd.Dispose()
            myCon.close()
        End Try
        myCon.close()
    End Sub

    Public Sub AdminTarrifSel()
        On Error Resume Next
        Dim myindex As Integer
        Dim tarrifcode As String
        Dim tdesc As String
        Dim tbasis As String
        Dim unit As String
        Dim tclass As String
        Dim darrastre As String
        Dim dstevedor As String
        Dim dother As String
        Dim farrastre As String
        Dim fstevedor As String
        Dim fother As String
        Dim scode As String
        Dim sname As String


        myindex = frmAdmin.dgTarrif.CurrentRow.Index
        tarrifcode = frmAdmin.dgTarrif.Item(0, myindex).Value
        tdesc = frmAdmin.dgTarrif.Item(1, myindex).Value
        tbasis = frmAdmin.dgTarrif.Item(2, myindex).Value
        unit = frmAdmin.dgTarrif.Item(3, myindex).Value
        tclass = frmAdmin.dgTarrif.Item(4, myindex).Value
        darrastre = frmAdmin.dgTarrif.Item(5, myindex).Value
        dstevedor = frmAdmin.dgTarrif.Item(6, myindex).Value
        dother = frmAdmin.dgTarrif.Item(7, myindex).Value
        farrastre = frmAdmin.dgTarrif.Item(8, myindex).Value
        fstevedor = frmAdmin.dgTarrif.Item(9, myindex).Value
        fother = frmAdmin.dgTarrif.Item(10, myindex).Value
        scode = frmAdmin.dgTarrif.Item(11, myindex).Value
        sname = frmAdmin.dgTarrif.Item(12, myindex).Value

        With frmAdmin
            .txtTarrifCode.Text = tarrifcode
            .txtTarrifDesc.Text = tdesc
            .txtTarrifBasis.Text = tbasis
            .txtTarrifUnit.Text = unit
            .cbTarrifClass.Text = tclass
            .darrastre.Text = darrastre
            .dstevedor.Text = dstevedor
            .dother.Text = dother
            .farrastre.Text = farrastre
            .fstevedor.Text = fstevedor
            .fother.Text = fother
            .cbTarrifServiceCode.Text = scode
            .temptariffcode.Text = tarrifcode
            .cbAlias.Text = sname
        End With


    End Sub

    Public Sub AdminTarrifAdd()
        With frmAdmin
            If .txtTarrifCode.Text = "" Or .txtTarrifDesc.Text = "" Or .txtTarrifBasis.Text = "" Or .txtTarrifUnit.Text = "" _
                Or .darrastre.Text = "" Or .dstevedor.Text = "" Or .dother.Text = "" Or .farrastre.Text = "" _
                Or .fstevedor.Text = "" Or .fother.Text = "" Or .cbAlias.Text = "" Then
                MsgBox("Please Fill up all important fields.")
            Else
                myCon.open()
                myCmd = New SqlCommand("INSERT INTO tbl_tarrif (t_code,t_desc,t_basis,t_unit,t_class,t_dom_arrastre,t_dom_stevedor,t_dom_other,t_for_arrastre,t_for_stevedor,t_for_other,t_scode,t_sname) VALUES ('" & .txtTarrifCode.Text & "','" & .txtTarrifDesc.Text & "','" & .txtTarrifBasis.Text & "','" & .txtTarrifUnit.Text & "','" & .cbTarrifClass.Text & "','" & .darrastre.Text & "','" & .dstevedor.Text & "','" & .dother.Text & "','" & .farrastre.Text & "','" & .fstevedor.Text & "','" & .fother.Text & "','" & .cbTarrifServiceCode.Text & "','" & .cbAlias.Text & "')", myCon)
                myCmd.ExecuteNonQuery()
                myCmd.Dispose()
                myCon.close()
            End If
        End With
    End Sub

    Public Sub AdminTarrifUpdate()
        myCon.open()
        With frmAdmin
            myCmd = New SqlCommand("UPDATE tbl_tarrif SET t_code = '" & .txtTarrifCode.Text & "',t_desc = '" & .txtTarrifDesc.Text & "',t_basis = '" & .txtTarrifBasis.Text & "',t_unit = '" & .txtTarrifUnit.Text & "',t_class = '" & .cbTarrifClass.Text & "',t_dom_arrastre = '" & .darrastre.Text & "',t_dom_stevedor = '" & .dstevedor.Text & "',t_dom_other = '" & .dother.Text & "',t_for_arrastre = '" & .farrastre.Text & "',t_for_stevedor = '" & .fstevedor.Text & "',t_for_other = '" & .fother.Text & "',t_scode = '" & .cbTarrifServiceCode.Text & "' ,t_sname = '" & .cbAlias.Text & "' WHERE t_code ='" & .temptariffcode.Text & "'", myCon)
            myCmd.ExecuteNonQuery()
            myCon.close()
        End With
    End Sub

    Public Sub AdminTarrifDel()
        myCon.open()
        myCmd = New SqlCommand("DELETE FROM tbl_tarrif WHERE t_code = '" & frmAdmin.temptariffcode.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub AdminBillTransDel()
        myCon.open()
        Dim mycmd1 = New SqlCommand("INSERT INTO tbl_bill_no (b_num) VALUES ('" & frmAdmin.lblBillno.Text & "')", myCon)
        mycmd1.ExecuteNonQuery()
        myCmd = New SqlCommand("DELETE FROM tbl_bill_trans WHERE bt_bill_num = '" & frmAdmin.lblBillno.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        Dim myCmd2 = New SqlCommand("DELETE FROM tbl_item_trans WHERE it_bill_num = '" & frmAdmin.lblBillno.Text & "'", myCon)
        myCmd2.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub AdminOrTransDel()
        myCon.open()
        Dim mycmd1 = New SqlCommand("INSERT INTO tbl_or_no (or_num) VALUES ('" & frmAdmin.lblOrTransno.Text & "')", myCon)
        mycmd1.ExecuteNonQuery()
        myCmd = New SqlCommand("DELETE FROM tbl_or_trans WHERE or_num = '" & frmAdmin.lblOrTransno.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        Dim myCmd2 = New SqlCommand("DELETE FROM tbl_orbill_trans WHERE ot_or_no = '" & frmAdmin.lblOrTransno.Text & "'", myCon)
        myCmd2.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub ExportBill()
        Try

            myCon.open()
            Dim ds As New DSBill
            myCmd = New SqlCommand("SunBill", myCon)

            myCmd.Parameters.Add(New SqlParameter("@date_from", SqlDbType.DateTime)).Value = frmAdmin.dtpBillFrom.Text
            myCmd.Parameters.Add(New SqlParameter("@date_to", SqlDbType.DateTime)).Value = frmAdmin.dtpBillTo.Text
            myCmd.CommandType = CommandType.StoredProcedure
            myAdapter.SelectCommand = myCmd
            myAdapter.Fill(ds, "SunBill")
            Dim crystal As New SunBill
            crystal.SetDataSource(ds)

            frmAdmin.SaveFileDialog1.Filter = "Excel files (*.xls)|*.xls"
            frmAdmin.SaveFileDialog1.Title = "Save Bill Report"
            frmAdmin.SaveFileDialog1.FileName = "Bill Report " + Date.Now.ToString("MM-dd-yyyy")
            frmAdmin.SaveFileDialog1.ShowDialog()
            Dim strExportFile As String = frmAdmin.SaveFileDialog1.FileName

            Dim CrExportOptions As ExportOptions
            Dim CrDiskFileDestinationOptions As New  _
            DiskFileDestinationOptions()
            Dim CrFormatTypeOptions As New ExcelFormatOptions
            CrDiskFileDestinationOptions.DiskFileName = strExportFile

            CrExportOptions = crystal.ExportOptions
            With CrExportOptions
                .ExportDestinationType = ExportDestinationType.DiskFile
                .ExportFormatType = ExportFormatType.ExcelWorkbook
                .DestinationOptions = CrDiskFileDestinationOptions
                .FormatOptions = CrFormatTypeOptions
            End With
            crystal.Export()

        Catch ex As Exception
            MsgBox(ex.Message)
            myCon.close()
        End Try
        myCon.close()
    End Sub

    Public Sub ExportOr()
        Try

            myCon.open()

            Dim ds As New DSBill

            myCmd = New SqlCommand("SunOr", myCon)
            myCmd.Parameters.Add(New SqlParameter("@date_from", SqlDbType.DateTime)).Value = frmAdmin.dtpOrFrm.Text
            myCmd.Parameters.Add(New SqlParameter("@date_to", SqlDbType.DateTime)).Value = frmAdmin.dtpOrTo.Text
            myCmd.CommandType = CommandType.StoredProcedure
            myAdapter.SelectCommand = myCmd
            myAdapter.Fill(ds, "SunOr")

            Dim crystal As New SunOr


            crystal.SetDataSource(ds)

            frmAdmin.SaveFileDialog1.Filter = "Excel files (*.xls)|*.xls"
            frmAdmin.SaveFileDialog1.Title = "Save OR Report"
            frmAdmin.SaveFileDialog1.FileName = "OR Report " + Date.Now.ToString("MM-dd-yyyy")
            frmAdmin.SaveFileDialog1.ShowDialog()
            Dim strExportFile As String = frmAdmin.SaveFileDialog1.FileName

            Dim CrExportOptions As ExportOptions
            Dim CrDiskFileDestinationOptions As New  _
            DiskFileDestinationOptions()
            Dim CrFormatTypeOptions As New ExcelFormatOptions
            CrDiskFileDestinationOptions.DiskFileName = strExportFile

            CrExportOptions = crystal.ExportOptions
            With CrExportOptions
                .ExportDestinationType = ExportDestinationType.DiskFile
                .ExportFormatType = ExportFormatType.ExcelWorkbook
                .DestinationOptions = CrDiskFileDestinationOptions
                .FormatOptions = CrFormatTypeOptions
            End With
            crystal.Export()

        Catch ex As Exception
            MsgBox(ex.Message)
            myCon.close()
        End Try
        myCon.close()
    End Sub

    Public Sub aliasname()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)

            myCmd = New SqlCommand("SELECT s_code,s_desc FROM tbl_service WHERE s_code = '" & frmAdmin.cbTarrifServiceCode.Text & "'", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)

            frmAdmin.cbAlias.DataSource = dt
            frmAdmin.cbAlias.DisplayMember = "s_desc"
        Catch ex As Exception
            MsgBox(ex.Message)


        Finally
            myCmd.Dispose()
            myCon.close()
        End Try
        myCon.close()
    End Sub

    Public Sub counterBill()
        Try
            myCon.open()
            Dim countbill As String
            Dim countor As String
            myCmd = New SqlCommand("SELECT COUNT(*) FROM tbl_bill_trans", myCon)
            countbill = myCmd.ExecuteNonQuery
            countbill = myCmd.ExecuteScalar
            frmAdmin.lblBillCount.Text = countbill

            Dim mycmd1 = New SqlCommand("SELECT COUNT(*) FROM tbl_or_trans", myCon)
            countor = mycmd1.ExecuteNonQuery
            countor = mycmd1.ExecuteScalar
            frmAdmin.lblOrCount.Text = countor
        Catch ex As Exception
            MsgBox(ex.Message)


        Finally
            myCmd.Dispose()
            myCon.close()
        End Try
        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub logbill()
                myCon.open()
        myCmd = New SqlCommand("INSERT INTO tbl_logs (b_desc)VALUES ('Added Bill Numbers From: " & frmAdmin.txtBFrom.Text & " To: " & frmAdmin.txtBTo.Text & " | Time: " & time & "')", myCon)
        myCmd.ExecuteNonQuery()

        myCon.close()
    End Sub

    Public Sub logor()
        myCon.open()
        myCmd = New SqlCommand("INSERT INTO tbl_logs (or_desc)VALUES ('Added OR Numbers From: " & frmAdmin.txtOFrom.Text & " To: " & frmAdmin.txtOto.Text & " | Time: " & time & "')", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub orupdatelog()
        myCon.open()
        myCmd = New SqlCommand("INSERT INTO tbl_logs (ortran_desc)VALUES ('OR Number: " & frmAdmin.lblOrTransno.Text & " UPDATED, Change Status to: " & frmAdmin.cbOrTransStatus.Text & " | Time: " & time & "')", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub billupdatelog()
        myCon.open()
        myCmd = New SqlCommand("INSERT INTO tbl_logs (btran_desc)VALUES ('BILL Number: " & frmAdmin.lblBillno.Text & " UPDATED, Change Status to: " & frmAdmin.cbBillStatus.Text & " | Time: " & time & "')", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub ordeletelog()
        myCon.open()
        myCmd = New SqlCommand("INSERT INTO tbl_logs (ortran_desc)VALUES ('OR Number: " & frmAdmin.lblOrTransno.Text & " DELETED | Time: " & time & "')", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub billdeletelog()
        myCon.open()
        myCmd = New SqlCommand("INSERT INTO tbl_logs (btran_desc)VALUES ('BILL Number: " & frmAdmin.lblBillno.Text & " DELETED | Time: " & time & "')", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    'Public Sub 

    '    myCon.open()
    '    myCmd = New SqlCommand("INSERT INTO tbl_logs (audit)VALUES ('" & frmLogin.txtUname.Text & " has Sign-in | Time: " & time & "')", myCon)
    '    myCmd.ExecuteNonQuery()
    '    myCon.close()
    'End Sub

    Public Sub faillog()
        myCon.open()
        myCmd = New SqlCommand("INSERT INTO tbl_logs (audit)VALUES ('Failed to Sign-in using this credentials USERNAME:" & frmLogin.txtUname.Text & "& PASSWORD: " & frmLogin.txtPass.Text & " | Time: " & time & "')", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub viewlog()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)
        myCmd = New SqlCommand("SELECT b_desc FROM tbl_logs WHERE b_desc <> '' ORDER BY id DESC", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmAdmin.DataGridView1.DataSource = dt.DefaultView

        Dim dt1 As New DataTable
        Dim ds1 As New DataSet
        ds1.Tables.Add(dt1)
        Dim myCmd1 = New SqlCommand("SELECT or_desc FROM tbl_logs WHERE or_desc <> '' ORDER BY id DESC", myCon)
        Dim myAdapter1 = New SqlDataAdapter(myCmd1)
        myAdapter1.Fill(dt1)
        frmAdmin.DataGridView2.DataSource = dt1.DefaultView

        Dim dt2 As New DataTable
        Dim ds2 As New DataSet
        ds2.Tables.Add(dt2)
        Dim myCmd2 = New SqlCommand("SELECT btran_desc FROM tbl_logs WHERE btran_desc <> '' ORDER BY id DESC", myCon)
        Dim myAdapter2 = New SqlDataAdapter(myCmd2)
        myAdapter2.Fill(dt2)
        frmAdmin.DataGridView3.DataSource = dt2.DefaultView

        Dim dt3 As New DataTable
        Dim ds3 As New DataSet
        ds3.Tables.Add(dt3)
        Dim myCmd3 = New SqlCommand("SELECT ortran_desc FROM tbl_logs WHERE ortran_desc <> '' ORDER BY id DESC", myCon)
        Dim myAdapter3 = New SqlDataAdapter(myCmd3)
        myAdapter3.Fill(dt3)
        frmAdmin.DataGridView4.DataSource = dt3.DefaultView

        Dim dt4 As New DataTable
        Dim ds4 As New DataSet
        ds4.Tables.Add(dt4)
        Dim myCmd4 = New SqlCommand("SELECT audit FROM tbl_logs WHERE audit <> '' ORDER BY id DESC", myCon)
        Dim myAdapter4 = New SqlDataAdapter(myCmd4)
        myAdapter4.Fill(dt4)
        frmAdmin.DataGridView5.DataSource = dt4.DefaultView

        myCon.close()

       
    End Sub

    Public Sub fix()


        Dim month As String = frmAdmin.DateTimePicker1.Value.Month
        Dim day As String = frmAdmin.DateTimePicker1.Value.Day
        Dim year As String = frmAdmin.DateTimePicker1.Value.Year
        Dim period As String
        Dim reference As String
        Dim ref As String


        If month = "1" And day <= 25 Then
            period = year + "001"
            reference = "RB010"
        ElseIf month = "1" And day > 25 Then
            period = year + "002"
            reference = "RB020"
        ElseIf month = "2" And day <= 25 Then
            period = year + "002"
            reference = "RB020"
        ElseIf month = "2" And day > 25 Then
            period = year + "003"
            reference = "RB030"
        ElseIf month = "3" And day <= 25 Then
            period = year + "003"
            reference = "RB030"
        ElseIf month = "3" And day > 25 Then
            period = year + "004"
            reference = "RB040"
        ElseIf month = "4" And day <= 25 Then
            period = year + "004"
            reference = "RB040"
        ElseIf month = "4" And day > 25 Then
            period = year + "005"
            reference = "RB050"
        ElseIf month = "5" And day <= 25 Then
            period = year + "005"
            reference = "RB050"
        ElseIf month = "5" And day > 25 Then
            period = year + "006"
            reference = "RB060"
        ElseIf month = "6" And day <= 25 Then
            period = year + "006"
            reference = "RB060"
        ElseIf month = "6" And day > 25 Then
            period = year + "007"
            reference = "RB070"
        ElseIf month = "7" And day <= 25 Then
            period = year + "007"
            reference = "RB070"
        ElseIf month = "7" And day > 25 Then
            period = year + "008"
            reference = "RB080"
        ElseIf month = "8" And day <= 25 Then
            period = year + "008"
            reference = "RB080"
        ElseIf month = "8" And day > 25 Then
            period = year + "009"
            reference = "RB090"
        ElseIf month = "9" And day <= 25 Then
            period = year + "009"
            reference = "RB090"
        ElseIf month = "9" And day > 25 Then
            period = year + "010"
            reference = "RB100"
        ElseIf month = "10" And day <= 25 Then
            period = year + "010"
            reference = "RB100"
        ElseIf month = "10" And day > 25 Then
            period = year + "011"
            reference = "RB110"
        ElseIf month = "11" And day <= 25 Then
            period = year + "011"
            reference = "RB110"
        ElseIf month = "11" And day > 25 Then
            period = year + "012"
            reference = "RB120"
        ElseIf month = "12" And day <= 31 Then
            period = year + "012"
            reference = "RB120"
        End If
        Dim no As String = CDbl(frmAdmin.TextBox1.Text)
        ref = reference + no

            myCon.open()


        myCmd = New SqlCommand("UPDATE tbl_bill_trans SET bt_date = '" & frmAdmin.DateTimePicker1.Text & "',bt_per = '" & period & "',bt_ref = '" & ref & "' WHERE bt_bill_num = '" & frmAdmin.TextBox1.Text & "'", myCon)
            myCmd.ExecuteNonQuery()
        MsgBox("OK")


      
            myCmd.Dispose()
            myCon.close()

    End Sub

   
End Module
